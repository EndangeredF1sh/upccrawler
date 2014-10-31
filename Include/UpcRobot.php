<?php
/*
 *   UpcRobot.php - 数字石大查询机器人
 *
 *   by: 李思洋
 *   版本: 0.2 (2014.10.31)
 *
 *   改动：
 *   2014-10-31  0.2  支持查询某一周课表，修正小bug
 *
 *   如果无法获取信息，会引发 PageNotFoundException
 *
 *   TODO 为了节省时间，应采取 Session 来保存登录信息，而不是每次调用都登录。
 *   TODO 如果使用 Session 保存登录信息，注意用户中途在其他设备登录，甚至更换密码的情况。
 *
 */

require_once('PageReader.php');
require_once('ClassTable.php');

// TODO 超时的处理

class UpcRobot {
    private $mReader;
    private $mLoginCode;
    
    private $mIsLogin = false;
    private $mIsLoginToCard = false;
    private $mIsLoginToLibrary = false;
    private $mIsLoginToJwxt = false;
    
    public function login($userName, $passWord) {
        // 检查是否重复登录
        if ($this->mIsLogin) return false;
        $this->mIsLogin = false;
        $this->mIsLoginToCard = false;
        $this->mIsLoginToLibrary = false;
        $this->mIsLoginToJwxt = false;
        
        $this->mReader = new PageReader();
        
        // 读取首页，获得登录码
        // 登录码类似于 name="lt" value="LT_dcpcas1_-363337-qVgzvwhuTGLBk1ErmuDP"
        $loginPage = $this->mReader->read('http://cas.upc.edu.cn/cas/login?service=http%3A%2F%2Fi.upc.edu.cn%2Fdcp%2Findex.jsp');
        preg_match('/name="lt" value="(.*)"/', $loginPage, $match);
        if (!isset($match[1])) throw new PageNotFoundException('Error loging http://i.upc.edu.cn');
        $loginCode = $match[1];

        // 在数字石大登录
        $tmp = $this->mReader->login('http://cas.upc.edu.cn/cas/login', array(
            'encodedService' => 'http%3a%2f%2fi.upc.edu.cn%2fdcp%2findex.jsp',
            'service' => 'http://i.upc.edu.cn/dcp/index.jsp',
            'serviceName' => 'null',
            'loginErrCnt' => '0',
            'username' => $userName,
            'password' => $passWord,
            'lt' => $loginCode,
            'autoLogin' => '0'
            ));
        
        // 处理用户名密码错误的情况
        if (strstr($tmp, '错误的用户名或密码') !== false) {
            $this->mReader->logOut();
            return false;
        }

        // 标记登录成功
        $this->mIsLogin = true;
        return true;
    }

    public function logOut() {
        $this->mReader->logOut();
        // 标记用户已经注销
        $this->mIsLogin = false;
        $this->mIsLoginToCard = false;
        $this->mIsLoginToLibrary = false;
        $this->mIsLoginToJwxt = false;
    }
        
    // TODO 获取用户姓名、学号、学院信息
    
    /*
     *   一卡通
     */ 
    public function loginToCard() {
        // 检查是否登录
        if (!$this->mIsLogin) return false;
        if (!$this->mIsLoginToCard) {
            // 调用数字石大接口进行登录
            $this->loginToApp(24406);
            
            // 标记一卡通中心登录成功
            $this->mIsLoginToCard = true;
        }
        return true;
    }
    
    public function getCardInfo() {
        // 检查是否登录，且登录到一卡通中心
        if (!$this->mIsLogin) return false;
        if (!$this->mIsLoginToCard && !$this->loginToCard()) return false;
        
        $basicInfo = $this->mReader->read('http://card.upc.edu.cn/CardManage/CardInfo/BasicInfo');

        $basicInfo = str_replace("\n", '', $basicInfo);
        
        $out['name'] = $this->extract('~名：</span><em>(.*?)</em>~', $basicInfo);
        $out['id'] = $this->extract('~学工号：</span><em>(.*?)</em>~', $basicInfo);
        $out['cardid'] = $this->extract('~卡号：</span><em> (.*?)</em>~', $basicInfo);
        $money1 = (double)$this->extract('~校园卡余额：</span><em>(.*?)</em>~', $basicInfo);
        $money2 = (double)$this->extract('~过渡余额：</span><em>(.*?)</em>~', $basicInfo);
        $out['balance'] = $money1 + $money2;
        
        $iout['lost'] = $this->extract('~挂失状态：</span><em> (.*?)</em>~', $basicInfo);
        $out['freeze'] = $this->extract('~冻结状态：</span><em>(.*?)</em>~', $basicInfo);
        
        $out['eaccount'] = $this->extract('~电子账户.*<span>(.*?)</span>~', $basicInfo);

        if ($out['name']===false) throw new PageNotFoundException();

        return $out;
    }
    
    public function getCardLog($startTime, $endTime) {
        // 检查是否登录，且登录到一卡通中心
        if (!$this->mIsLogin) return false;
        if (!$this->mIsLoginToCard && !$this->loginToCard()) return false;
        
        // TODO 将两个时间转换为规范的形式
		// 时间格式：2014-01-01
        
        // 抓取文本
		$url = 'http://card.upc.edu.cn/CardManage/CardInfo/TrjnList?';
		if (!empty($startTime)) {
			$url .= 'beginTime='.$startTime.'&endTime='.$endTime.'&type=1&';
			$first = $this->mReader->read($url);
		} else {
			$first = $this->mReader->read($url);
		}

		// 判断是否没有流水
		if (strstr($first, '没有流水记录') !== false) {
			return array();
		}

		// 获取流水数量
		// 页数 = 流水数 / 10
		$first = str_replace("\n", '', $first);
		$first = str_replace("\r", '', $first);
		// TODO 正则表达式不支持中文？拿结束时间顶一下（所以结束时间格式必须正确）
		$count = (int) $this->extract('#<td colspan="5">.*?'.$endTime.'.*?(\d.*?)\s*?</td>#', $first);
		$pages = (int)(($count+9) / 10);

		$r = array();

		for ($i = 1; $i <= $pages; $i++) {
		    // 注意：$url后面已经带 ? 和 & 了。
			$txt = $this->mReader->read($url.'pageindex='.$i);
		    $txt = str_replace("\n", '', $txt);
		    $txt = str_replace("\r", '', $txt);
		    $txt = strstr($txt, '总记录数为');
			
			preg_match_all('#<tr.*?td>(.*?)</td.*?td>(.*?)</td.*?td>(.*?)</td.*?red">(.*?)</s.*?ue">(.*?)</s.*?/tr>#', $txt, $m);
			
			$n = count($m[0]);
			
			for ($j = 0; $j < $n; $j++) {
			    $x = '';
			    $x['time'] = trim($m[1][$j]);
			    $x['shop'] = trim($m[2][$j]);
			    $x['reason'] = trim($m[3][$j]);
			    $x['value'] = trim($m[4][$j]);
			    $x['total'] = trim($m[5][$j]);
			    
			    array_push($r, $x);
			}
		}

        return $r;
    }

    /*
     *   图书馆
     */ 
    public function loginToLibrary() {
        // 检查是否登录
        if (!$this->mIsLogin) return false;
        if (!$this->mIsLoginToLibrary) {
            // 调用数字石大接口进行登录
            //$url = $this->getRealUrl('http://i.upc.edu.cn/dcp/forward.action?path=dcp/apps/sso/jsp/ssoDcpSelf&appid=1186');
            $this->loginToApp(1186);
            
            // 标记图书馆登录成功
            $this->mIsLoginToLibrary = true;
        }
        return true;
    }
    
    public function getBorrowedInfo() {
        // 检查是否登录
        if (!$this->mIsLogin) return false;
        if (!$this->mIsLoginToLibrary && !$this->loginToLibrary()) return false;

        $st = $this->mReader->read('http://211.87.177.4/reader/book_lst.php');
        $st = str_replace("\n", '', $st);

        if (strstr($st, '您的该项记录为空！') !== false) {
            return array('over'=>false, 'count'=>0, 'books'=>array());
        } else {
            // TODO 图书名称和作者用的是 &#x0000 的格式！
            $r = array();
            $r['over'] = (strstr($st, '超期！不得续借！') !== false);
            $r['count'] = $cnt = $this->extract('~当前借阅\( <b class="blue">(.*?)</b>~', $st);

            $pattern = '#<tr>\s*?<td class="whitetext".*?>(?P<bookid>\d*)</td>\s*?<td.*?><a.*?>(?P<name>.*?)</a> / (?P<author>.*?)</td>\s*?<td.*?>(?P<borrowdate>.*?)</td>\s*?<td(?:.*?>){1,2}(?P<deadline>\d*-\d*-\d*).*?</td>\s*?<td.*?>(?P<renew>.*?)</td>\s*?<td.*?>(?P<position>.*?)</td>\s*?<td.*?>(?P<others>.*?)</td>\s*?<td.*?>.*?</td>\s*?</tr>#';
            $keyword = array('bookid', 'name', 'author', 'borrowdate', 'deadline', 'renew', 'position', 'others');
            preg_match_all($pattern, $st, $m);

            $r['books'] = array();

            for ($i = 0; $i<$cnt; $i++) {
                foreach ($keyword as $k) {
                    $r['books'][$i][$k]=$m[$k][$i];
                }
            }

            return $r;
        }
    }
    
    /*
     *   教务系统
     */ 
    // 教务系统得手动弄一下 Cookie
    private $mJwxtCookie;

    public function loginToJwxt() {
        // 检查是否登录
        if (!$this->mIsLogin) return false;
        if (!$this->mIsLoginToJwxt) {
            // 调用数字石大接口进行登录
            $sys = $this->loginToApp(1180);
			//echo 'login';

            // 获取 JSESSIONID
            $this->mJwxtCookie = $this->extract('/Set\-Cookie: (.*?);/iU', $sys);

            // 教务系统登录时要调用一个文件，然后才算做真正登录
            $this->mReader->post('http://jwxt.upc.edu.cn/jwxt/Logon.do?method=logonBySSO',array(),
                array(CURLOPT_HTTPHEADER => array('Content-Type: application/x-www-form-urlencoded',
                                                    'Host: 211.87.177.1',
                                                    'Content-Length: 0')
            ));

            // 标记教务系统登录成功
            $this->mIsLoginToJwxt = true;
        }
        return true;
    }
    
    public function getTable($term, $week) {
        // 检查是否登录到教务系统
        if (!$this->mIsLogin) return false;
        if (!$this->mIsLoginToJwxt && !$this->loginToJwxt()) return false;

        // $term 格式：2014-2015-1
        $tab = $this->mReader->read('http://jwxt.upc.edu.cn/jwxt/tkglAction.do?method=goListKbByXs&istsxx=no&xnxqh='.$term.'&zc='.$week,
            array(CURLOPT_COOKIE => $this->mJwxtCookie
        ));

        //$r = $this->extract('#(<table id="kbtable".*?</table>)#msi', $tab);
        //return $r;
        $t = new ClassTable($tab);
        return $t;
    }
    
    public function getTableByStu($term, $id, $week) {
        // URL: http://jwxt.upc.edu.cn/jwxt/tkglAction.do?method=goListKb&type=16
        // 获取方式：POST
        // 格式：usertype=2&xnxqh=2014-2015-1&xsName=%E5%BE%90%E8%BF%8E%E8%8E%B9&abc=1&type=16&zc=&xqid=&selectUrl=queryKbByStudent.jsp&findType=cx&type2=1&xs0101id=1302010205

        // 检查是否登录到教务系统
        if (!$this->mIsLogin) return false;
        if (!$this->mIsLoginToJwxt && !$this->loginToJwxt()) return false;

        // $term 格式：2014-2015-1
        $tab = $this->mReader->post('http://jwxt.upc.edu.cn/jwxt/tkglAction.do?method=goListKb&type=16&zc='.$week,
            array(
                'usertype' => 2,
                'xnxqh' => $term,
                'abc' => 1,
                'type' => 16,
                'findType' => 'cx',
                'type2' => 1,
                'xs0101id' => $id
            ),
            array(CURLOPT_COOKIE => $this->mJwxtCookie
        ));

        //echo '<textarea>'.$tab.'</textarea>';

        //$r = $this->extract('#(<table id="kbtable".*?</table>)#msi', $tab);
        //return $r;
        $t = new ClassTable($tab);
        return $t;
    }

    public function getTableByClassroom($term, $id, $week) {
        // URL: http://jwxt.upc.edu.cn/jwxt/tkglAction.do?method=goListKb&type=4
        // 获取方式：POST
        // 格式：xnxqh=2014-2015-1&xqid=&gnqid=&jzwid=&classroomID=00305&classname=&abc=1&type=4&zc=&xqid=&selectUrl=queryKbByClassroom.jsp&findType=cx&type2=1
		// 教室 ID 如何确定？
		// TODO 轮询
		// TODO 借用查询

        // 检查是否登录到教务系统
        if (!$this->mIsLogin) return false;
        if (!$this->mIsLoginToJwxt && !$this->loginToJwxt()) return false;

        // $term 格式：2014-2015-1
        $tab = $this->mReader->post('http://jwxt.upc.edu.cn/jwxt/tkglAction.do?method=goListKb&type=16&zc='.$week,
            array(
                'xnxqh' => $term,
				'classroomID' => $id,
                'abc' => 1,
                'type' => 4,
                'findType' => 'cx',
                'type2' => 1
            ),
            array(CURLOPT_COOKIE => $this->mJwxtCookie
        ));

        //echo '<textarea>'.$tab.'</textarea>';

        //$r = $this->extract('#(<table id="kbtable".*?</table>)#msi', $tab);
        //return $r;
        $t = new ClassTable($tab);
        return $t;
    }

    public function getClassrooms() {
        // URL: http://jwxt.upc.edu.cn/jwxt/tkglAction.do?method=goListKb&type=4
        // 获取方式：POST
        // 格式：xnxqh=2014-2015-1&xqid=&gnqid=&jzwid=&classroomID=00305&classname=&abc=1&type=4&zc=&xqid=&selectUrl=queryKbByClassroom.jsp&findType=cx&type2=1
		// 教室 ID 如何确定？

        // 检查是否登录到教务系统
        if (!$this->mIsLogin) return false;
        if (!$this->mIsLoginToJwxt && !$this->loginToJwxt()) return false;

		//$result = array();
		$cnt = 0;

		for ($i=1; $i<=15; $i++) {
			$tab = $this->mReader->post('http://jwxt.upc.edu.cn/jwxt/ggxx/selectJs.jsp?id=classroomID&name=classname&type=1',
				array(
					'PageNum' => $i
				),
				array(CURLOPT_COOKIE => $this->mJwxtCookie
			));

			preg_match_all('#onclick=\"javascript:selectJs\(\'\?gdjs=(\d*?)&jsmc=(.*?)\'\);return#ims',$tab,$match);

			for ($j=0; $j<count($match[1]); $j++) {
				$result[$cnt][0] = $match[1][$j];
				$result[$cnt][1] = $match[2][$j];
				$cnt++;
			}
		}

        return $result;
    }

    public function getScore($term = '') {
        // 检查是否登录到教务系统
        if (!$this->mIsLogin) return false;
        if (!$this->mIsLoginToJwxt && !$this->loginToJwxt()) return false;

        $score = array();
        $pages = 1;

        for ($i = 1; $i<=$pages; $i++) {
            // 查询成绩需要 POST 到 /jwxt/xszqcjglAction.do?method=queryxscj
            // kksj 表示开课时间，格式为 2014-2015-1（可以为空）
            // 还有一个叫 ok 的，不取任何值
            // 其他参数（kcxz课程性质、kcmc课程名称、xsfs显示方式）暂时忽略不计
            $page = $this->mReader->post('http://jwxt.upc.edu.cn/jwxt/xszqcjglAction.do?method=queryxscj',
                array('kksj' => $term, 'ok'=>'','kcxz'=>'','kcmc'=>'','xsfs'=>'', 'PageNum'=>$i),
                array(CURLOPT_COOKIE => $this->mJwxtCookie)
            );

            $this->extractScore($score, $page);

            // 获得总页数
            if ($pages == 1) {
                $pages = (int) $this->extract('#value=(\d*).*?末页#', $page);
            }

        }

        // 顺便查一下学分绩
        //$this->averScore = $this->extract('#(<font color=red size="2">.*&nbsp;&nbsp;&nbsp;]</font>)#ms', $page);
        preg_match('#-->.*?已修读.*?<span>(\d*).*?<span>(\d*).*?<span>(\d*).*?<span>(\d*).*?学分绩<span>(.*?)</span>#ms', $page, $m);

        if (!isset($m[0]) || empty($m[0])) 
            $credits = false;
        else 
            $credits = array(
                'total' => $m[1],
                'A' => $m[2],
                'B' => $m[3],
                'C' => $m[4],
                'aver' => $m[5]
            );

        return array($score,$credits);
    }

    /*
    public function getCredits() {
        // 检查是否登录到教务系统
        if (!$this->mIsLogin) return false;
        if (!$this->mIsLoginToJwxt && !$this->loginToJwxt()) return false;
        if (!$this->mIsLogin || !$this->mIsLoginToJwxt) return false;

        $page = $this->mReader->post('http://jwxt.upc.edu.cn/jwxt/xszqcjglAction.do?method=queryxscj',
            array('kksj' => '2009-2010-1', 'ok'=>'','kcxz'=>'','kcmc'=>'','xsfs'=>''),
            array(CURLOPT_COOKIE => $this->mJwxtCookie)
        );
        $page = str_replace("\n",'',$page);

        preg_match('#-->.*?已修读.*?<span>(\d*).*?<span>(\d*).*?<span>(\d*).*?<span>(\d*).*?学分绩<span>(.*?)</span>#ms', $page, $m);

        if (!isset($m[0]) || empty($m[0])) return false;

        return array(
            'total' => $m[1],
            'A' => $m[2],
            'B' => $m[3],
            'C' => $m[4],
            'aver' => $m[5]
        );

        // 在查成绩的时候应该顺便把学分绩查出来。如果没有就查一下。
        if ($this->averScore === '') $this->getScore('2009-2010-1');
        return $this->averScore;
    }
 */

    private function extractScore(&$score, $txt) {
        // 提取成绩信息
        /* $match = '#<tr.*?(?:.*?</td>){3}<td.*?>(.*?)</td><td.*?>(.*?)</td><td.*?><a.*?>(.*?)</a></td><td.*?>(.*?)</td><td.*?>(.*?)</td><td.*?>(.*?)</td><td.*?>(.*?)</td><td.*?>(.*?)</td><td.*?>(.*?)</td><td.*?>(.*?)</td>.*?</tr>#'; */

        $match = '#<tr.*?(?:.*?</td>){3}<td.*?>(?P<term>.*?)</td><td.*?>(?P<subject>.*?)</td><td.*?><a.*?>(?P<score>.*?)</a></td><td.*?>(?P<scoreflag>.*?)</td><td.*?>(?P<style>.*?)</td><td.*?>(?P<type>.*?)</td><td.*?>(?P<hour>.*?)</td><td.*?>(?P<credit>.*?)</td><td.*?>(?P<examtype>.*?)</td><td.*?>(?P<refreshterm>.*?)</td>.*?</tr>#';
        $keyword = array('term', 'subject', 'score', 'scoreflag', 'style', 'type', 'hour', 'credit', 'examtype', 'refreshterm');
        
        preg_match_all($match, $txt, $m);
        if (isset($m['term'])) {
            $count = count($m['term']);

            for ($i = 0; $i < $count; $i++) {
                $p = array();
                foreach ($keyword as $k) {
                    $p[$k] = $m[$k][$i];
					$p[$k] = str_replace('&nbsp;','',$p[$k]);
                }
                array_push($score, $p);
            }

        }
        //return $this->extract('#(<table border="1"[^>]*mxh.*?</table>)#ms', $txt);
    }
     
    /*
     * 从数字石大中抓取课程表。这个结果是错误的。
    public function testClassTable() {
        // 仅供测试
        return $this->mReader->read('http://i.upc.edu.cn/report/Report-EntryAction.do?reportId=2c7beb59-4510-4988-9e03-ae2c2b0bfa3d');
    }
    
    public function testScore() {
        // 仅供测试
        return $this->mReader->read('http://i.upc.edu.cn/report/Report-EntryAction.do?reportId=785f9959-6541-4b87-bbab-0fda6ebb9107');
    }
     */

    /*
     *   其他
     */      
    /*
    private function getRealUrl($fakeUrl) {
        $tmp = $this->mReader->read($fakeUrl);

        //$url2 = $this->extract('/Location: (.*)/i', $tmp);
        //$header = $this->extract('/(.*?)</ims', $tmp);
        //echo $fakeUrl.':<textarea>'.$tmp.'</textarea><br />';

        $url2 = $this->extract('/href="(.*?)"/', $tmp);
        // TODO: 检查失败的情况
        $tmp = $this->mReader->read($url2);
        $url2 = $this->extract('/href="(.*?)"/', $tmp);

        return $url2;
    }
     */

    private function loginToApp($id) {
        $url = 'http://i.upc.edu.cn/dcp/forward.action?path=dcp/apps/sso/jsp/ssoDcpSelf&appid='.$id;
        $h = array(CURLOPT_HEADER => 1);

        // 有时调用要跳转两次，有时是一次，有时直接进入……
        do {
            $tmp = $this->mReader->read($url, $h);
            $url = $this->extract('/href="(.*?)"/', $tmp);
        } while ($this->extract('/(<title>CAS)/', $tmp));

        return $tmp;
    }
    
    private function extract($pattern, $str) {
        preg_match($pattern, $str, $match);
        if (isset($match[1])) return $match[1]; else return false;
    }
}

<?php
/*
 *    ClassTable.php - 对教务系统那个 eggpain 的课程表进行处理
 *
 *    支持两种表格：一种是课程表，另一种是列表
 *
 *    未来将加入与周数有关的功能
 *
 */

class ClassTable {
    private $orinText;
    private $isInit1 = false;
    private $isInit2 = false;

    private $table;
    private $list;
    private $memo;
	private $borrow;

    public function __construct($orinText) {
        $this->orinText = $orinText;
    }

    public function getTable() {
        $this->init1();
        return $this->table;
    }

    public function getList() {
        $this->init1();

        if (!$this->isInit2) {
            $this->list = array();

            for ($day = 0; $day < 7; $day++) {
                for ($c = 0; $c <=5; $c++) {
                    foreach ($this->table[$day][$c] as $class) {
                        $name = $class['name'];
                        if (!isset($this->list[$name])) $this->list[$name] = array();

                        array_push($this->list[$name], $class);
                    }
                }
            }

            $this->isInit2 = true;
        }

        return $this->list;
    }

    public function getMemo() {
        $this->init1();
        return $this->memo;
    }

    public function getBorrow() {
        $this->init1();
        return $this->borrow;
    }

    private function init1() {
        if ($this->isInit1) return;

        $orin = str_replace('&nbsp;','',$this->orinText);
        $pattern = '#<tr>.*?<td.*?none;">(.*?)</div.*?/td>.*?<td.*?none;">(.*?)</div.*?/td>.*?<td.*?none;">(.*?)</div.*?/td>.*?<td.*?none;">(.*?)</div.*?/td>.*?<td.*?none;">(.*?)</div.*?/td>.*?<td.*?none;">(.*?)</div.*?/td>.*?<td.*?none;">(.*?)</div.*?/td>.*?</tr>#msi';
        preg_match_all($pattern, $orin, $tab);

        $this->table = array();

        // 提取具体的课程信息
        $pattern = '#(?P<name>.*?)<br>(?P<class>.*?)<br>(?P<teacher>.*?)<br>(<nobr>){0,1}  (?P<week>.*?)(?:<nobr>){0,1}<br>(?P<classroom>.*?)<br>.*?<br>#i';
        $keywords = array('name','class','teacher','week','classroom');
        for ($day = 1; $day <= 7; $day++) {
            // $day = 1 表示星期日，2 表示星期一……这是由教务系统的输出来决定的。
            $correctDay = $day-1;
            for ($c = 0; $c <= 5; $c++) {
                // 第几节大课，仍然由教务系统决定
                preg_match_all($pattern, $tab[$day][$c], $match);

                $cnt = count($match['name']);
                if ($cnt) {
                    for ($i = 0; $i < $cnt; $i++) {
                        foreach ($keywords as $k) {
                            $this->table[$correctDay][$c][$i][$k] = $match[$k][$i];
                        }

                        $this->table[$correctDay][$c][$i]['weekday'] = $correctDay;
                        $this->table[$correctDay][$c][$i]['index'] = $c;
                    }
                } else {
                    // 此时无课
                    $this->table[$correctDay][$c] = array();
                }
            }
        }
        
        // 获取备注
        //$pattern = '#备注.*?(.*?)</td>#msi';
		$pattern = '#<td   height="28" colspan="7"  align="center">(.*?)</td>#msi';
        preg_match($pattern, $orin, $match);
        $this->memo = isset($match[1])? trim($match[1]):'';
        //$this->memo = $this->orinText;

		// TODO If it is a tabel for room, get the information of meetings.


        $this->isInit1 = true;
    }
}

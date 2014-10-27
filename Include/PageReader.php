<?php
/*
 *   PageReader.php - 页面抓取工具
 *
 *   PageReader 类会抓取网页的代码。
 *   PageNotFoundException 类在发生 404 的时候触发。
 */

// Cookie 临时存放目录。请勿放在网站目录下！
// 请勿以斜线结尾
define('TMPPATH', '/tmp/upcrobot');
//define('TMPPATH', 'D:\wamp\www\tmp\upcrobot');

class PageReader {
    private $mCookiePath;
    private $mIsLogin = false;
    public $timeOut = 5;

    public function read($url, $options = null) {
        $c = curl_init();

        curl_setopt($c, CURLOPT_URL, $url);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($c, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($c, CURLOPT_HEADER, 0);
        curl_setopt($c, CURLOPT_TIMEOUT, $this->timeOut);

        if ($this->mIsLogin) { // && !isset($options[CURLOPT_COOKIE])) {
    		curl_setopt($c, CURLOPT_COOKIEJAR, $this->mCookiePath);
            curl_setopt($c, CURLOPT_COOKIEFILE, $this->mCookiePath);
        }
        
		if (isset($options) && is_array($options)) {
            curl_setopt_array($c, $options);
        }

        $data = curl_exec($c);
        //echo 'header = '.curl_getinfo($c, CURLINFO_HEADER_OUT).'<br />';

        if ($data === FALSE) throw new PageNotFoundException('Error reading '.$url);

        curl_close($c);

        return $data;
    }

    public function post($url, $postField, $options = null) {
        $opt = array(
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => http_build_query($postField)
        );
        if (is_array($options)) $opt = $opt + $options;

        return $this->read($url, $opt);
        /*
		$c = curl_init($url);
		
		curl_setopt($c, CURLOPT_HEADER, 0);
		curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($c, CURLOPT_POST, 1);
		curl_setopt($c, CURLOPT_POSTFIELDS, http_build_query($postField));
		if (isset($header) && is_array($header)) {
            curl_setopt($c, CURLOPT_HTTPHEADER, $header);
		}
		
        if ($this->mIsLogin) {
    		curl_setopt($c, CURLOPT_COOKIEJAR, $this->mCookiePath);
            curl_setopt($c, CURLOPT_COOKIEFILE, $this->mCookiePath);
        }
        
		$data = curl_exec($c);

        if ($data === FALSE) throw new PageNotFoundException();

		curl_close($c);
         */
		return $data;    
    }

    public static function extractString($expr, $str, $d = '~') {
        preg_match($d.$expr.$d, $str, $match);

        if (!empty($match) && isset($match[1]))
            return $match[1];
        else
            return false;
    }


    public function isLogin() {
        return $mIsLogin;
    }
    
    public function login($url, $postField, $options = null) {
        if (!is_dir(TMPPATH)) mkdir(TMPPATH, 0777);
    
		$this->mCookiePath = TMPPATH.'/cookie_'.time().'.txt';
		$this->mIsLogin = true;

        return $this->post($url, $postField, $options);
        /*
		$c = curl_init($url);
		
		curl_setopt($c, CURLOPT_HEADER, 0);
		curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($c, CURLOPT_POST, 1);
		curl_setopt($c, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($c, CURLOPT_POSTFIELDS, http_build_query($postField));
		curl_setopt($c, CURLOPT_COOKIEJAR, $this->mCookiePath);
        curl_setopt($c, CURLOPT_COOKIEFILE, $this->mCookiePath);
		if (isset($options) && is_array($options)) {
            curl_setopt($c, CURLOPT_HTTPHEADER, $header);
		}
		$data = curl_exec($c);

        if ($data === FALSE) throw new PageNotFoundException();

        curl_close($c);

		return $data;
         */
    }
    
    public function logOut() {
        // TODO: 注销
        unlink($this->mCookiePath);
        $this->mLogin = false;
    }
    
    /*
    public function read2($url, $header = '') {
        $c = curl_init();

        curl_setopt($c, CURLOPT_URL, $url);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($c, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($c, CURLOPT_HEADER, 1);

        
		if (isset($header) && is_array($header)) {
            curl_setopt($c, CURLOPT_HTTPHEADER, $header);
        }
         
        //curl_setopt($c, CURLOPT_COOKIE, $header);
                
        if ($this->mIsLogin) {
    		curl_setopt($c, CURLOPT_COOKIEJAR, $this->mCookiePath);
            curl_setopt($c, CURLOPT_COOKIEFILE, $this->mCookiePath);
        }

        $data = curl_exec($c);

        if ($data === FALSE) throw new PageNotFoundException();

        curl_close($c);

        return $data;
    }
     */
/*
    public function postJson($url, $json, $header) {
		$c = curl_init($url);
		
		curl_setopt($c, CURLOPT_HEADER, 0);
		curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
		if (isset($header) && !empty($header)) {
            curl_setopt($c, CURLOPT_HTTPHEADER, $header);
		}
		curl_setopt($c, CURLOPT_POST, 1);
		curl_setopt($c, CURLOPT_POSTFIELDS, $json);
		
        if ($this->mIsLogin) {
    		curl_setopt($c, CURLOPT_COOKIEJAR, $this->mCookiePath);
            curl_setopt($c, CURLOPT_COOKIEFILE, $this->mCookiePath);
        }
        
		$data = curl_exec($c);

        if ($data === FALSE) throw new PageNotFoundException();

		curl_close($c);
		
		return $data;    
    }    
*/    

/*
    public function readLines($url, $maxLine) {
        return read($url);
    }
*/
    
/*
    private $lines;

    private function readLineCallback($cl, $data) {
        echo htmlspecialchars($data, ENT_QUOTES);
        return strlen($data);
    }

    public function readLines($url, $maxLine) {
        $lines = 0;
        $c = curl_init();
        curl_setopt($c, CURLOPT_URL, $url);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($c, CURLOPT_WRITEFUNCTION, '$this->readLineCallback');
        curl_setopt($c, CURLOPT_BUFFERSIZE, 512);
        $data = curl_exec($c);
        curl_close($c);
        return $data;
    }
*/    
}

class PageNotFoundException extends Exception {}


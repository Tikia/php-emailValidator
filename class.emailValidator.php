<?php
class emailValidator {
    private $_larrow="<span style='color: #d02020; font-size: 1.5em; font-weight: bolder;'>&nbsp;&larr;</span>";
    private $_rarrow="<span style='color: #209114; font-size: 1.5em; font-weight: bolder;'>&nbsp;&rarr;</span>";
    private $_isLog;
    private $_log_mx;
    private $_log_smtp;
    private $_fromName;
    private $_fromDomain;
    private $_port;
    private $_maxConnectionTimeout;
    private $_maxStreamTimeout;

    public function __construct() {
		$this->_isLog=false;
		$this->_log_smtp=array();
		$this->_log_mx=array();
        $this->_fromName = 'user';
        $this->_fromDomain = 'yourdomain.com';
        $this->_port = 25;
        $this->_maxConnectionTimeout = 10;
        $this->_maxStreamTimeout = 5;
    }
    public function setFrom($email) {
		$tmp=explode("@",$email);
        $this->_fromName = $tmp[0];
        $this->_fromDomain = $tmp[1];
    }
    public function setLog($active=true) {
        $this->_isLog = $active;
    }
    public function getLogMx() {
		if(count($this->_log_mx)>0)
	   	    return "<strong>MX record found :</strong><br />-&nbsp;".implode("<br />-&nbsp;",$this->_log_mx);
		else
			return "<strong>No MX record found</strong>";
    }
    public function getLogSmtp() {
		if(count($this->_log_smtp)>0 || count($this->_log_mx)>0)
	         return "<strong>Log SMTP :</strong>".implode("",$this->_log_smtp);
 		else
			return "&nbsp;";
   }
    public function resetLog() {
		$this->_log_smtp=array();
		$this->_log_mx=array();
    }
    public function setPort($port) {
        $this->_port = $port;
    }
    public function setEmailFrom($email) {
        list($this->_fromName, $this->_fromDomain) = $this->parseEmail($email);
    }
    public function setConnectionTimeout($seconds) {
        $this->_maxConnectionTimeout = $seconds;
    }
    public function setStreamTimeout($seconds) {
        $this->_maxStreamTimeout = $seconds;
    }
    public function isValid($email) {
        return (false !== filter_var($email, FILTER_VALIDATE_EMAIL));
    }
    public function parseEmail(&$email) {
        return sscanf($email, "%[^@]@%s");
    }
    public function getMXrecords($hostname,$addDomainIfEmpty=true) {
        $mxhosts = array();
        $mxweights = array();
        if(getmxrr($hostname, $mxhosts, $mxweights)) {
            array_multisort($mxweights, $mxhosts);
        }
		if(count($mxhosts)==0 && $addDomainIfEmpty) {
			$mxhosts[] = $hostname;
		}
		if($this->_isLog) {
			$this->_log_mx=$mxhosts;
		}
		
        return $mxhosts;
    }
    public function callSmtp($email,$mxs,$sender=false) {
        $result = false;
		$fp = false;
		$timeout = ceil($this->_maxConnectionTimeout / count($mxs));
		foreach($mxs as $host) {
			$fp = @stream_socket_client("tcp://" . $host . ":" . $this->_port, $errno, $errstr, $timeout);
			if($this->_isLog) {
				if(count($this->_log_smtp)>0) {
					$this->_log_smtp[]="<hr />";
					$this->_log_smtp[]="Connect to tcp://" . $host . ":" . $this->_port.$this->_rarrow;
				}
				else {
					$this->_log_smtp[]="<br />Connect to tcp://" . $host . ":" . $this->_port.$this->_rarrow;
				}
			}
			if($fp) {
				stream_set_timeout($fp, $this->_maxStreamTimeout);
				stream_set_blocking($fp, 1);
				$code = $this->_fsockGetResponseCode($fp);
				if($code == '220') {
					$result = 'domain';
					break;
				}
				else {
					fclose($fp);
					$fp = false;
				}
			}
			else {
				$this->_log_smtp[]="<br />" . $errno ." - ". $errstr.$this->_larrow;
			}
		}
		if($fp) {
			if($sender) {
				list($fromName, $fromDomain) = $this->parseEmail($email);
				$this->_fsockquery($fp, "HELO " . $fromDomain);
				$this->_fsockquery($fp, "MAIL FROM: <" . $fromName . '@' . $fromDomain . ">");
			}
			else {
				$this->_fsockquery($fp, "HELO " . $this->_fromDomain);
				$this->_fsockquery($fp, "MAIL FROM: <" . $this->_fromName . '@' . $this->_fromDomain . ">");
			}
			$code = $this->_fsockquery($fp, "RCPT TO: <" . $email . ">");
			$this->_fsockquery($fp, "RSET");
			$this->_fsockquery($fp, "QUIT");
			fclose($fp);
			if($code == '250') {
				/**
				 * http://www.ietf.org/rfc/rfc0821.txt
				 * 250 Requested mail action okay, completed
				 * email address was accepted
				 */
				$result = 'email';
			}
			elseif ($code == '450' || $code == '451' || $code == '452') {
				/**
				 * http://www.ietf.org/rfc/rfc0821.txt
				 * 450 Requested action not taken: the remote mail server
				 *     does not want to accept mail from your server for
				 *     some reason (IP address, blacklisting, etc..)
				 *     Thanks Nicht Lieb.
				 * 451 Requested action aborted: local error in processing
				 * 452 Requested action not taken: insufficient system storage
				 * email address was greylisted (or some temporary error occured on the MTA)
				 * i believe that e-mail exists
				 */
				$result = 'email';
			}
		}

        return $result;
    }
    public function check($email) {
		$this->resetLog();
        $result = 'notValid';
        if($this->isValid($email)) {
	        $result = 'valid';
            list($user, $domain) = $this->parseEmail($email);
            $mxs = $this->getMXrecords($domain);
			$ret=$this->callSmtp($email,$mxs);
			if($ret!==false)
				$result=$ret;
			else
				$result='noServer';
        }
        return $result;
    }
    private function _fsockquery(&$fp, $query) {
        stream_socket_sendto($fp, $query . "\r\n");
		if($this->_isLog) {
			$this->_log_smtp[]="<br />".htmlentities($query).$this->_rarrow;
		}

        return $this->_fsockGetResponseCode($fp);
    }
    private function _fsockGetResponseCode(&$fp) {
		$reply=stream_get_line($fp, 1);
		$status=stream_get_meta_data($fp);
		if($status['unread_bytes']>0) {
			$reply.=stream_get_line($fp, $status['unread_bytes'],"\r\n");
		}
		if($this->_isLog) {
			$this->_log_smtp[]="<br />".htmlentities($reply).$this->_larrow;
		}
		preg_match('/^(?<code>[0-9]{3}) (.*)$/ims', $reply, $matches);
		$code=isset($matches['code']) ? $matches['code'] : false;
		return $code;
	}
}
?>
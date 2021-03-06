<?php
include_once ("model/meta_model.php");
include_once ("tools/util.php");
include_once ("tools/passwd.php");

class smbpasswd {
    var $metafp;
    var $metafilename;

    function smbpasswd($metadata_path = "") {

		if (!is_null_or_empty_string($metadata_path)) {
			@$this->metafp = passwd::open_or_create ( $metadata_path );
			$this->metafilename = $metadata_path;
		}
    }
    function user_exists($username) {
        system("sudo pdbedit -L | grep -q " . $username, $return_var);
        return !$return_var;
    }
    function meta_exists($username) {
        return passwd::exists ( @$this->metafp, $username );
    }
    function meta_find_user_for_mail($email) {
        return passwd::meta_find_user_for_mail($this->metafp, $email);
    }
    function get_metadata() {
        return passwd::get_metadata(@$this->metafp);
    }
    function get_users() {
        return self::stdout("sudo pdbedit -L | sed -E 's/^([^:]+):.*/\\1/'");
    }

    function user_add($username, $password, &$error_msg = NULL) {
        $err_code = self::errcode('(echo ' . escapeshellarg($password) . '; echo ' .
            escapeshellarg($password) . ') | sudo smbpasswd -s -a ' .
            escapeshellarg($username), 'user_add',$error_msg);
        return !$err_code;
    }

    function meta_add(meta_model $meta_model) {
        return passwd::meta_add($this->metafp, $meta_model);
    }

    function user_delete($username, &$error_msg = NULL) {
        $err_code = self::errcode('sudo smbpasswd -x ' . escapeshellarg($username),
            'user_delete',$error_msg);
        return !$err_code;
    }

    function meta_delete($username) {
        return passwd::delete ( @$this->metafp, $username, @$this->metafilename );
    }

    function user_update($username, $password, &$error_msg = NULL) {
        $err_code = self::errcode('(echo ' . escapeshellarg($password) . '; echo ' .
            escapeshellarg($password) . ') | sudo smbpasswd -s ' .
            escapeshellarg($username), 'user_update', $error_msg);
        return !$err_code;
    }

    function user_must_change_password($username, &$error_msg = NULL) {
        $err_code = self::errcode("sudo net sam set pwdmustchangenow " .
            $username . " yes", $error_msg);
        return !$err_code;
    }

    function user_self_service($username, $old, $new, &$error_msg = NULL) {
        $err_code = self::errcode('(echo ' . escapeshellarg($old) . '; echo ' .
            escapeshellarg($new) . '; ' . 'echo ' . escapeshellarg($new) .
            ') | sudo -u ' . escapeshellarg($username) . ' /usr/bin/smbpasswd -s',
            'user_self_service',$error_msg);
        return !$err_code;
    }

    function meta_update(meta_model $meta_model) {
        $this->meta_delete ( $meta_model->user );
        $this->meta_add ( $meta_model );
        return false;
    }

    static function errcode($cmd,$logprefix="cmd",&$error_msg=NULL)
    {
        $tmpfname = tempnam(sys_get_temp_dir(),$logprefix.'_');

        $descriptorspec = array(
           0 => array("pipe", "r"),
           1 => array("pipe", "w"),
           2 => array("file", $tmpfname, "a")
        );

        $cwd = sys_get_temp_dir();
        $env = array();
        $env['PATH'] = getenv('PATH');
        // TODO in config
        $env['LANG'] = "en_US.UTF-8";

        while (@ ob_end_flush());

        $process = proc_open($cmd, $descriptorspec, $pipes, $cwd, $env);

        if (is_resource($process)) {

            $return_value = proc_close($process);

            if($return_value)
                if($error_msg !== NULL)
                    $error_msg = lastline($tmpfname);

            return $return_value;
        }

        if(!defined('STDERR')) define('STDERR', fopen('php://stderr', 'w'));
        fwrite(STDERR, 'Could not open process'. PHP_EOL);
    }

    static function stdout($cmd,$logprefix="cmd")
    {
        $tmpfname = tempnam(sys_get_temp_dir(),$logprefix.'_');

        $descriptorspec = array(
           0 => array("pipe", "r"),
           1 => array("pipe", "w"),
           2 => array("file", $tmpfname, "a")
        );

        $cwd = sys_get_temp_dir();
        $env = array();
        $env['PATH'] = getenv('PATH');
        // TODO in config
        $env['LANG'] = "en_US.UTF-8";

        while (@ ob_end_flush());

        $process = proc_open($cmd, $descriptorspec, $pipes, $cwd, $env);

        if (is_resource($process)) {

            $out = array ();
            $fd2 = $pipes[1];
            $i = 0;

            while (($line = fgets($fd2)) !== false) {
                $out [$i] = dropn($line); // dropping LF
                $i ++;
            }

            fclose($fd2);

            $return_value = proc_close($process);

            if(!$return_value)
                return $out;
        }

        if(!defined('STDERR')) define('STDERR', fopen('php://stderr', 'w'));
        fwrite(STDERR, 'Could not open process'. PHP_EOL);
    }
}


?>

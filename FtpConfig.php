<?php
abstract class Custom_Controller_Batch_FtpConfig extends Custom_Controller_Batch_Abstract
{
    protected $version = '1.0';
    protected $readLogFtpFailPath = null;
    protected $logFtpFailPath = null;
    protected $key, $value, $oldValue, $publishDate;
    protected $path = '/files/public/setting/';
    protected $useBackup = true;
    protected $loginFailed = 'FTP_LOGIN_FAILED';
    protected $backupExtension = '.bak';

    protected function streamOptions(){
        return array('ftp' => array('overwrite' => true));
    }

    protected function files(){
        return [
            'contact_assess_*.ini',
            'contact_contact_*.ini',
            'contact_kasi-jigyou_*.ini',
            'contact_kasi-kyojuu_*.ini',
            'contact_request_*.ini',
            'contact_request-kasi-jigyou_*.ini',
            'contact_request-kasi-kyojuu_*.ini',
            'contact_request-uri-jigyou_*.ini',
            'contact_request-uri-kyojuu_*.ini',
            'contact_uri-jigyou_*.ini',
            'contact_uri-kyojuu_*.ini',
            'api.ini'
        ];
    }

    protected function getBackUpPath(){
        return $this->path.'backups_'.$this->getVersionString();
    }

    protected function getVersionString(){
        return str_replace('.','_', $this->version);
    }

    protected function getBackUpFullPath(){
        return $this->getBackUpPath().DIRECTORY_SEPARATOR;
    }

    protected function setBackupFolder($baseCommand){
        $backupPath = $this->getBackUpPath();
        $command = $baseCommand.$backupPath;
        if(is_dir($command)){
            return;
        }
        mkdir($command,0777, true);
    }

    /**
     * Set new Content
     * @param string $content
     * @return array
     */
    protected function setContent($content){

        // define text string
        $newContent = "";

        $isChanged = false;

        //convert to array
        $data = preg_split('/\r\n|\r|\n/', $content);

        //search all line as key => value, update value
        foreach($data as $k => $v){
            // if not found key, continue to new line
            if (strpos($v, $this->key) === false)  continue;
            // if have old value, will check if not found old value => continue
            if(strpos($v, $this->oldValue) === false) continue;
            $data[$k] = str_replace($this->oldValue,$this->value,$v);
            $isChanged = true;
        }

        //update back as text string
        foreach($data as $k => $v){
            $newContent .= "$v\n";
        }

        return [
            'content' => $newContent,
            'isChanged' => $isChanged
        ];
    }

    /**
     * Check allow by filename
     * @param $fileName
     * @return bool
     */
    protected function isAllow($fileName){
        $allRegex = $this->files();

        foreach($allRegex as $v){
            $reg = '/^' . str_replace('*', '\\w+', $v) . '$/';
            if (preg_match($reg, $fileName, $matches)) {
                return true;
            }
        }

        return false;
    }


    /**
     * Get all files from a path in FTP
     * @param string $host
     * @param string $user
     * @param string $password
     * @param string $path
     * @return array
     * @throws Exception
     */
    protected function getFilesFromPath($host,$user,$password, $path = null){
        $conn = ftp_connect($host);
        $loggedIn = ftp_login($conn,  $user, $password);

        if (!$loggedIn){
            throw new Exception($this->loginFailed);
        }

        $passiveMode = ftp_pasv($conn, true);

        // get FTP Path
        if(is_null($path)){
            $path = $this->path;
        }

        // Get lists
        $list  = ftp_nlist($conn, $path);

        $files   = array();

        foreach ($list as $file)
        {
            if($this->ftp_isDir($conn,$file)) continue;

            $files[] = $file;
        }

        ftp_close($conn);

        return $files;
    }

    protected function ftp_isDir($connect_id,$dir)
    {
        if(ftp_chdir($connect_id,$dir))
        {
            ftp_cdup($connect_id);
            return true;

        }
        else
        {
            return false;
        }
    }

    /**
     * Read from log
     * @return array
     */
    protected function readFromLog(){
        $data = array();
        try{
            $filename = $this->getReadLogFtpFailPath();
            $content = file_get_contents($filename);
            $data = preg_split('/\r\n|\r|\n/', $content);
            foreach($data as $k => $v){
                if(is_numeric($v)) continue;
                unset($data[$k]);
            }
        }
        catch(\Exception $e){

        }
        return $data;
    }

    /**
     * set log file to read
     * @param string $filename
     */
    protected function setReadLogFtpFailPath($filename){
        $this->readLogFtpFailPath =  APPLICATION_PATH . '/../log/'  . $filename  . '.log';
    }

    /**
     * get log
     * @return string|null
     */
    protected function getReadLogFtpFailPath(){
        return $this->readLogFtpFailPath;
    }

    /**
     * set log file to write
     * @throws Zend_Log_Exception
     */
    protected function setFtpLogFail(){
        $config = array(Zend_Log::CRIT, '=');

        $this->logFtpFailPath = APPLICATION_PATH . '/../log/' . get_class($this) . '_FtpLoginFail_' . time()  . '.log';

        $filename = $this->logFtpFailPath;

        if (@file_exists($filename) || false !== @file_put_contents($filename, '', FILE_APPEND)) {
            @chmod($filename, 0777);
        }

        $writer = new Zend_Log_Writer_Stream($filename);

        $filter = new Zend_Log_Filter_Priority($config[0], $config[1]);
        $writer->addFilter($filter);

        $formatter = new Zend_Log_Formatter_Simple("%message%" . PHP_EOL);
        $writer->setFormatter($formatter);

        $this->_logger->addWriter($writer);
    }
}
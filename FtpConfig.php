<?php
abstract class Custom_Controller_Batch_FtpConfig extends Custom_Controller_Batch_Abstract
{
    protected $version = '1.0';
    protected $readLogFtpFailPath = null;
    protected $logFtpFailPath = null;
    protected $readLogFtpSuccessPath = null;
    protected $logFtpSuccessPath = null;
    protected $key = ['domain','api_url'];
    protected $value, $publishDate;
    protected $path = '/files/public/setting/';
    protected $useBackup = true;
    protected $loginFailed = 'FTP_LOGIN_FAILED';
    protected $backupExtension = '.bak';
    protected $folderLog = 'ftp_log';

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

    protected function filesNoSchemaUrl(){
        return [
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
     * @param bool $removeSchema
     * @return array
     */
    protected function setContent($content, $removeSchema = false){

        // define text string
        $newContent = "";

        $isChanged = false;

        //convert to array
        $data = preg_split('/\r\n|\r|\n/', $content);

        //search all line as key => value, update value
        foreach($data as $k => $v){
            // if not found keys, continue to new line
            if(strpos($v, 'domain') === false && strpos($v, 'api_url') === false) continue;

            $newData = $this->value;

            try{
                $setDomain = $this->replaceDomain($v,$newData, $removeSchema);
                $data[$k] = $setDomain['data'];
                $isChanged = $setDomain['isChanged'];
            }
            catch (Exception $e){
                // nah
            }
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

    protected function getLogFullPath($filename){
        return APPLICATION_PATH . '/../log/'. $this->folderLog . '/' . $filename  . '.log';
    }

    /**
     * set log file to read
     * @param string $filename
     */
    protected function setReadLogFtpFailPath($filename){
        $this->readLogFtpFailPath =  $this->getLogFullPath($filename);
    }

    /**
     * get log
     * @return string|null
     */
    protected function getReadLogFtpFailPath(){
        return $this->readLogFtpFailPath;
    }

    /**
     * set log file to read
     * @param string $filename
     */
    protected function setReadLogFtpSuccessPath($filename){
        $this->readLogFtpSuccessPath =  $this->getLogFullPath($filename);
    }

    /**
     * get log
     * @return string|null
     */
    protected function getReadLogFtpSuccessPath(){
        return $this->readLogFtpSuccessPath;
    }

    /**
     * set log file to write
     * @param $publishDate
     * @throws Zend_Log_Exception
     */
    protected function setFtpLog($publishDate = ''){
        $configs = array(
            'FtpFail' => array(Zend_Log::CRIT, '='),
            'FtpSuccess' => array(Zend_Log::NOTICE, '=')
        );

        foreach($configs as $key => $config){
            $path = 'log'.$key.'Path';
            $filename = get_class($this) . '_'.$key.'_' . time().'_'. $publishDate;
            $this->{$path} = $filename = $this->getLogFullPath($filename);

            if (@file_exists($filename) || false !== @file_put_contents($filename, '', FILE_APPEND)) {
                @chmod($filename, 0777);
            }

            $writer = new Zend_Log_Writer_Stream($filename);

            $filter = new Zend_Log_Filter_Priority($config[0], $config[1]);
            $writer->addFilter($filter);

            $formatter = new Zend_Log_Formatter_Simple("%message%" . PHP_EOL);
            $writer->setFormatter($formatter);

            $this->logger()->addWriter($writer);
        }
    }

    protected function ftpSuccess($message){
        return $this->logger()->notice($message);
    }

    protected function ftpFail($message){
        return $this->logger()->crit($message);
    }

    protected function contains($needle, $haystack)
    {
        return strpos($haystack, $needle) !== false;
    }

    protected function removeSchemaUrl($url){
        return preg_replace("(^https?://)", "", $url );
    }

    /**
     * @param string $line
     * @param string $newValue
     * @param bool $removeSchema
     * @return mixed
     */
    function replaceDomain($line,$newValue, $removeSchema = false){

        $isChanged = false;

        if($removeSchema){
            $newValue = $this->removeSchemaUrl($newValue);
        }

        $startRegex = '/^';
        $endRegex = '$/';
        $regex = '('.implode('|', $this->key).')( ?= ?)(...*)';

        preg_match($startRegex.$regex.$endRegex,$line,$re);

        $key = $re[1];

        $url = preg_replace('/["\']/','',$re[3]);

        $fakeUrl = $url;
        $parsed = parse_url($fakeUrl);
        if (empty($parsed['scheme'])) {
            $fakeUrl = 'http://' . ltrim($fakeUrl, '/');
        }
        $oldValue = parse_url($fakeUrl, PHP_URL_HOST);

        $originalParts = parse_url($url);
        if(isset($originalParts['scheme']) && !empty($originalParts['scheme'])){
            $oldValue =  $originalParts['scheme']. '://' .$oldValue;
        }

        if($oldValue != $newValue){
            $isChanged = true;
        }

        $data = str_replace($oldValue,$newValue,$line);

        return [
            'data' => $data,
            'isChanged' => $isChanged
        ];
    }

    public function array_partition(array $a, $np, $pad = true)
    {
        $np = (int)$np;
        if ($np <= 0) {
            trigger_error('partition count must be greater than zero', E_USER_NOTICE);
            return array();
        }
        $c = count($a);
        $per_array = (int)floor($c / $np);
        $rem = $c % $np;
        // special case for an empty array
        if ($c === 0) {
            if ($pad) {
                $result = array_fill(0, $np, array());
            } else {
                $result = array();
            }
        }
        // array_chunk will work if the remainder is 0 or np-1, or if there are more partitions than elements in the array
        elseif ($rem === 0 || $rem == $np - 1 || $np >= $c) {
            // if there is a remainder each partition will need 1 more
            $result = array_chunk($a, $per_array + ($rem > 0 ? 1 : 0));
            // if necessary, pad out the array with empty arrays
            if ($pad && $np > $c) {
                $result = array_merge($result, array_fill(0, $np - $c, array()));
            }
        }
        // use the slower case if 0 < remainder < np-1 and there are more elements in the array than paritions
        // ($rem > 0 && $rem < $np - 1 && $np < $c)
        else {
            $split = $rem * ($per_array + 1);
            // the first $rem partitions will have $per_array + 1
            $result = array_chunk(array_slice($a, 0, $split), $per_array+1);
            // the rest of the partitions will have per_array
            $result = array_merge($result, array_chunk(array_slice($a, $split), $per_array));
            // no padding is necessary if the conditions for this case are met
        }
        return $result;
    }


}
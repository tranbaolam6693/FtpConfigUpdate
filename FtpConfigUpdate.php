<?php

//php index.php development app FtpConfigUpdate {key} {old_value] {new_value} {date} {log_file_name}
// key, old_value, new_value, date là required, log_file_name tùy.
// Example:
// > php index.php development app FtpConfigUpdate domain 127.0.0.1 127.0.0.2 2019-05-03 Batch_FtpConfigUpdate_FtpLoginFail_1556786800
// Sẽ tìm hết tất cả key là domain trong list file files()
// Tiến hành replace old_value => new_value
// {date} là ngày cuối cùng publish

// nếu truyền {log_file_name} vào thì sẽ chỉ lấy list id từ cái file này
//(tên file log này được in ra trong sourcecode/log/Batch_FtpConfigUpdate_info.log)

// log info /log/Batch_FtpConfigUpdate_info.log
// log error /log/Batch_FtpConfigUpdate_error.log

class Batch_FtpConfigUpdate extends Custom_Controller_Batch_Abstract
{

    private $version = '1.0';
    private $readLogFtpFailPath = null;
    private $logFtpFailPath = null;

    private $key, $value, $oldValue, $publishDate;
    private $path = '/files/public/setting/';
    private $useBackup = false;
    private $loginFailed = 'FTP_LOGIN_FAILED';

    private function streamOptions(){
        return array('ftp' => array('overwrite' => true));
    }

    private function files(){
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

    /**
     * @param array $args
     * @throws Exception
     */
    protected function _action($args)
    {
        error_reporting(E_ALL ^ E_WARNING);

        if(!isset($args[1]) || !isset($args[2]) ){
            throw new Exception('Need key to update');
        }

        if( !isset($args[2]) ){
            throw new Exception('Need old value to check');
        }

        if(!isset($args[3])){
            throw new Exception('Need new value to update');
        }

        if(!isset($args[4])){
            throw new Exception('Need last publish date');
        }

        if($args[2] == $args[3]){
            throw new Exception('Old and new value cannot be the same');
        }

        $this->key = $args[1];
        $this->oldValue = $args[2];
        $this->value = $args[3];
        $this->publishDate = $args[4];

        // Nếu có truyền log file vô, đọc từ file này lấy ra list ids company bị lỗi
        // Nếu k truyền, lấy hết company
        if(isset($args[5])){
            $this->setReadLogFtpFailPath($args[5]);
            $ids = $this->readFromLog();
        }

        // set log to write company fail
        $this->setFtpLogFail();

        // write info
        $this->info("UPDATE CONFIG KEY `$this->key`: $this->oldValue => $this->value");
        $this->info("DATE: ". date('Y-m-d H:i:s', time()));
        $this->info("Log FTP Fail: $this->logFtpFailPath");
        $this->info('======================================================');

        $companyTable = App_Model_DbTable_Company::master();

        //build query select
        $select = $companyTable->select()->from('company',array(
            'company.id',
            'company.ftp_server_name',
            'company.ftp_user_id',
            'company.ftp_password',
            'company.contract_type'
        ));
        $select->setIntegrityCheck(false);
        if(isset($ids) && count($ids) > 0 ){
            $select->where('company.id IN (?)', $ids);
        }
        $select->joinLeft('associated_company_hp', 'associated_company_hp.company_id = company.id', array());
        $select->joinRight('hp_page','associated_company_hp.current_hp_id = hp_page.hp_id', array());
        $select->where('hp_page.published_at < ?', $this->publishDate);
        $select->where('company.delete_flg = ?', 0);
        $select->order('hp_page.published_at DESC');
        $select->group(array('company.id'));
        //end build query select

        $companies = $companyTable->setAutoLogicalDelete(false)->fetchAll($select);

        foreach($companies as $company){
            // pass site demo
            if($company->contract_type == App_Model_List_CompanyAgreementType::CONTRACT_TYPE_DEMO) continue;
            $textMessage = "Company: $company->id"  ;
            $this->info("$textMessage");
            try{
                //update
                $this->update(
                    $company->ftp_server_name,
                    $company->ftp_user_id,
                    $company->ftp_password
                );
                $this->info("*** STATUS : Done");
            }
            catch(\Exception $e){
                $this->info("*** STATUS : Failed");
                $this->info($e->getMessage());
                //if($e->getMessage() == $this->loginFailed){
                    $this->ftpFail($company->id);
                //}
            }
            $this->info('======================================================');
        }
    }

    /**
     * Update all files
     * @param $host
     * @param $user
     * @param $password
     * @throws Exception
     */
    protected function update($host,$user,$password){

        $messages = array();
        $fileEffected = array();
        $fileUpdated = array();
        $fileUpdatedCount = 0;

        // Creates a stream context resource with the defined options
        $stream_context = stream_context_create($this->streamOptions());

        // get all files from path
        $files = $this->getFilesFromPath($host,$user,$password);

        foreach($files as $file){
            $fileName = basename($file);

            if(!$this->isAllow($fileName)) continue;

            $command = "ftp://$user:$password@$host".$file;

            //read
            $fp = fopen($command,"r");
            $content = fread($fp,filesize($command));
            fclose($fp); unset($fp);

            //update new content
            $setContent = $this->setContent($content);

            // if file is effect
            if($setContent['isChanged']){
                $fileEffected[] = $file;

                //backup
                if($this->useBackup){
                    copy($command, $command.'.'.$this->version.'.bak',$stream_context);
                }

                $newContent = $setContent['content'] ;
                try{
                    //overwrite
                    $fp = fopen($command,"w", 0, $stream_context);
                    fwrite($fp,$newContent);
                    fclose($fp);

                    $fileUpdated[] = $file;
                    $fileUpdatedCount++;

                    $messages[$file] = "> Updated";
                }
                catch(\Exception $e){
                    $messages[$file] = "> Failed. Error: ".$e->getMessage();
                }

            }

        }

        // show effected file
        $fileEffectedCount = count($fileEffected);
        $this->info("*** EFFECTED_FILE: $fileEffectedCount");

        if(count($fileEffected)>0){
            foreach($fileEffected as $file){
                $this->info("- $file");
            }
        }

        // show updated files
        $this->info("*** UPDATED/EFFECTED FILES: $fileUpdatedCount/$fileEffectedCount");

        if(count($fileUpdated)>0){
            $this->info('Detail Info:');
            foreach($fileUpdated as $file){
                $this->info("- $file");
                if(!isset($messages[$file])) continue;
                $this->info($messages[$file]);
            }
        }
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
    private function isAllow($fileName){
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
     * Get all files from path in FTP
     * @param $host
     * @param $user
     * @param $password
     * @return array
     * @throws Exception
     */
    private function getFilesFromPath($host,$user,$password){
        $conn = ftp_connect($host);
        $loggedIn = ftp_login($conn,  $user, $password);

        if (!$loggedIn){
            throw new Exception($this->loginFailed);
        }

        // get FTP Path
        $path = $this->path;

        // Get lists
        $nlist  = ftp_nlist($conn, $path);
        $rawlist    = ftp_rawlist($conn, $path);

        $files   = array();

        for ($i = 0; $i < count($nlist) - 1; $i++)
        {
            if($rawlist[$i][0] == 'd')
            {
                continue;
            }

            $files[] = $nlist[$i];
        }

        ftp_close($conn);

        return $files;
    }


    /**
     * Write message to log
     * @param string $message
     */
    public function ftpFail($message = '') {
        $this->_logger->crit($message);
    }

    /**
     * Read from log
     * @return array
     */
    public function readFromLog(){
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
    private function setReadLogFtpFailPath($filename){
        $this->readLogFtpFailPath =  APPLICATION_PATH . '/../log/'  . $filename  . '.log';
    }

    /**
     * get log
     * @return string|null
     */
    private function getReadLogFtpFailPath(){
        return $this->readLogFtpFailPath;
    }

    /**
     * set log file to write
     * @throws Zend_Log_Exception
     */
    private function setFtpLogFail(){
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
<?php

/**
 * php index.php development app FtpConfigUpdate {type} {param} {old_value] {new_value}
 *
 * VD:
 * php index.php development app FtpConfigUpdate update 2019-05-03 domain 172.23.0.2 172.23.0.3
 * php index.php development app FtpConfigUpdate fix Batch_FtpConfigUpdate_FtpFail_1557107377 domain 172.23.0.2 172.23.0.3
 */

// log info /log/Batch_FtpConfigUpdate_info.log
// log error /log/Batch_FtpConfigUpdate_error.log

class Batch_FtpConfigUpdate extends Custom_Controller_Batch_FtpConfig
{
    protected $type = 'update';
    protected static $FIX_STATE = 'fix';
    protected static $UPDATE_STATE = 'update';
    protected $currentPart = false;
    protected $maxPart = 6;

    /**
     * @param array $args
     * @throws Exception
     */
    protected function _action($args)
    {
        set_time_limit(0);
        error_reporting(E_ALL ^ E_WARNING);

        if(!isset($args[1])){
            throw new Exception('Need set type `update` or `fix`');
        }

        $updateState = self::$UPDATE_STATE;
        $fixState = self::$FIX_STATE;

        // can exolode
        if(strpos($args[1], '_') !== false) {
            $states = explode('_',$args[1]);
            $currentState = $states[0];
            if($currentState == self::$UPDATE_STATE){
                if(isset($states[1]) && is_numeric($states[1]) && $states[1] > 0 && $states[1] <= $this->maxPart){
                    $this->currentPart = $states[1];
                }
            }
        }
        else {
            $currentState = $args[1];
        }

        if(!in_array($currentState,[$updateState, $fixState])){
            throw new Exception("Only support type `$updateState` or `$fixState`");
        }

        $this->type = $currentState;

        if(!isset($args[2])){
            throw new Exception('Need value to update');
        }

        $this->value = $args[2];

        $currentDate = date('Y-m-d H:m:s', time());

        switch($this->type){
            case $fixState:
                if(!isset($args[3])){
                    throw new Exception('Need set file FTP fail list');
                }
                if (!$this->contains('Batch_FtpConfigUpdate_FtpFail',$args[3])) {
                    throw new Exception('Invalid. File name must be `Batch_FtpConfigUpdate_FtpFail_{time_run_batch}_{publish_date}`.');
                }

                // read publish date from file name
                $extData = explode('_',$args[3]);
                $publishDateRawData = end($extData);
                $dt = new DateTime();
                $dt->setTimestamp($publishDateRawData);
                $this->publishDate = $dt->format('Y-m-d H:m:s');

                $this->setReadLogFtpFailPath($args[3]);
                // đọc từ file này lấy ra list ids company bị lỗi
                $ids = $this->readFromLog();
                break;
            default:
                $this->publishDate = $currentDate;
                if(isset($args[3])){
                    $ids = explode(',',$args[3]);
                }
        }

        // set log to write company success/fail
        $publishDateObject = \DateTime::createFromFormat('Y-m-d H:m:s', $this->publishDate);
        if(!$publishDateObject){
            throw new Exception('Please set publish date as format `Y-m-d H:m:s`');
        }
        $publishDateTimestamp = $publishDateObject->getTimestamp();
        $this->setFtpLog($publishDateTimestamp);

        // write info
        $this->info("UPDATE CONFIG KEY `". implode(',', $this->key)."`: $this->value");
        if($this->type == $fixState){
            $this->info("FIX BATCH FROM FILE: ". $this->readLogFtpFailPath);
        }
        $this->info("DATE: $currentDate");
        $this->info("Log FTP Success: ". basename($this->logFtpSuccessPath));
        $this->info("Log FTP Fail: ". basename($this->logFtpFailPath));
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
        if(isset($ids) && count($ids) > 0){
            $select->where('company.id IN (?)', $ids);
        }
//        $select->joinLeft('associated_company_hp', 'associated_company_hp.company_id = company.id', array());
//        $select->joinRight('hp_page','associated_company_hp.current_hp_id = hp_page.hp_id', array());
        // publish date will only work with state = `update`, not `fix`
//        if($this->type == $updateState){
//            $select->where('hp_page.published_at < ?', $this->publishDate);
//        }
        $select->where('company.delete_flg = ?', 0);
        //$select->order('hp_page.published_at DESC');
//        $select->group(array('company.id'));
        //end build query select

        $data = $companyTable->setAutoLogicalDelete(false)->fetchAll($select);

        // cannot use toArray(), it convert all to array, but we need to use item as object
        $companies = [];
        foreach($data as $company){
            $companies[] = $company;
        }

        if($this->type == self::$UPDATE_STATE && $this->currentPart !== false){
            $companies = $this->array_partition($companies,$this->maxPart);
            $companies = isset($companies[$this->currentPart - 1])
                ? $companies[$this->currentPart - 1]
                : [];
        }

        $success = 0;
        $fail = 0;
        if(count($companies) > 0) {
            foreach ($companies as $company) {
                // pass site demo
                if ($company->contract_type == App_Model_List_CompanyAgreementType::CONTRACT_TYPE_DEMO) continue;
                $textMessage = "Company: $company->id";
                $this->info("$textMessage");
                try {
                    //update
                    $this->update(
                        $company->ftp_server_name,
                        $company->ftp_user_id,
                        $company->ftp_password
                    );
                    $this->info("*** STATUS : Done");
                    $this->ftpSuccess($company->id);
                    $success++;
                } catch (\Exception $e) {
                    $this->info("*** STATUS : Failed");
                    $this->info($e->getMessage());
                    $this->ftpFail($company->id);
                    $fail++;
                }
                $this->info('======================================================');
            }
        }
        $this->info('** SUMMARY UPDATED:');
        $this->info("Success: $success");
        $this->info("Fail: $fail");

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

        $baseCommand = "ftp://$user:$password@$host";

        if($this->useBackup){
            $this->setBackupFolder($baseCommand);
        }

        foreach($files as $file){
            $fileName = basename($file);

            if(!$this->isAllow($fileName)) continue;

            $command = $baseCommand.$file;

            //read
            $fp = fopen($command,"r");
            $content = fread($fp,filesize($command));
            fclose($fp); unset($fp);

            $removeSchema = in_array($fileName,$this->filesNoSchemaUrl());

            //update new content
            $setContent = $this->setContent($content,$removeSchema);

            // if file is effect
            if($setContent['isChanged']){
                $fileEffected[] = $file;

                $skipBackup = false;
                //backup
                if($this->useBackup){
                    $bkFileName = $fileName.$this->backupExtension;
                    $desPath = $this->getBackUpFullPath().$bkFileName;
                    $desCommand = $baseCommand.$desPath;
                    if(file_exists($desCommand) ) {
                        $skipBackup = true;
                    }
                    else copy($command, $desCommand, $stream_context);
                }

                $newContent = $setContent['content'] ;
                try{
                    //overwrite
                    $fp = fopen($command,"w", 0, $stream_context);
                    fwrite($fp,$newContent);
                    fclose($fp);

                    $fileUpdated[] = $file;
                    $fileUpdatedCount++;

                    if($this->useBackup && $skipBackup == true){
                        $messages[$file] = "> Updated. But skipped backup (existed)";
                    }
                    else $messages[$file] = "> Updated";
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
}
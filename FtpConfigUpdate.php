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

class Batch_FtpConfigUpdate extends Custom_Controller_Batch_FtpConfig
{
    /**
     * @param array $args
     * @throws Exception
     */
    protected function _action($args)
    {
        error_reporting(E_ALL ^ E_WARNING);

        if(!isset($args[1])){
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

        // set log to write company success/fail
        $this->setFtpLog();

        // write info
        $this->info("UPDATE CONFIG KEY `$this->key`: $this->oldValue => $this->value");
        $this->info("DATE: ". date('Y-m-d H:i:s', time()));
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
                $this->ftpSuccess($company->id);
            }
            catch(\Exception $e){
                $this->info("*** STATUS : Failed");
                $this->info($e->getMessage());
                $this->ftpFail($company->id);
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

            //update new content
            $setContent = $this->setContent($content);

            // if file is effect
            if($setContent['isChanged']){
                $fileEffected[] = $file;

                //backup
                if($this->useBackup){
                    $bkFileName = $fileName.$this->backupExtension;
                    $desPath = $baseCommand.$this->getBackUpFullPath().$bkFileName;
                    copy($command, $desPath,$stream_context);
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
}
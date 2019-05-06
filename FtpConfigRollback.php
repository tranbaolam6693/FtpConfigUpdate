<?php

class Batch_FtpConfigRollback extends Custom_Controller_Batch_FtpConfig
{

    /**
     * @param array $args
     * @throws Exception
     */
    protected function _action($args)
    {
        error_reporting(E_ALL ^ E_WARNING);

        if(!isset($args[1]) ){
            throw new Exception('Need version to rollback');
        }

        if(!isset($args[2])){
            throw new Exception('Need set file FTP list to rollback');
        }

        if (!$this->contains('Batch_FtpConfigUpdate_FtpSuccess',$args[2])) {
            throw new Exception('Invalid. File name must be `Batch_FtpConfigUpdate_FtpSuccess_{time_run_batch}_{publish_date}`.');
        }

        $this->version = $args[1];

        // read publish date from file name
        $extData = explode('_',$args[2]);
        $publishDateRawData = end($extData);
        $dt = new DateTime();
        $dt->setTimestamp($publishDateRawData);
        $this->publishDate = $dt->format('Y-m-d H:m:s');
        // end

        $this->setReadLogFtpFailPath($args[2]);
        $ids = $this->readFromLog();

        // write info
        $this->info("ROLLBACK CONFIG VERSION `$this->version`");
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
        //$select->where('hp_page.published_at < ?', $this->publishDate);
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
                $this->logger()->crit($company->id);
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

        // Creates a stream context resource with the defined options
        $stream_context = stream_context_create($this->streamOptions());

        $baseCommand = "ftp://$user:$password@$host";

        // get all files from path
        $files = $this->getFilesFromPath($host,$user,$password,$this->getBackUpFullPath());


        foreach($files as $backupFile){
            //not allow extension
            if (strpos($backupFile, $this->backupExtension) === false)  continue;

            $fileName = str_replace($this->backupExtension, '', basename($backupFile));

            $file = $this->path.$fileName;

            if(!$this->isAllow($fileName)) continue;

            $originalCommand = $baseCommand.$file;

            $desCommand = $baseCommand. $backupFile;

            $this->info("Copy $backupFile > $file");

            if(!file_exists($desCommand) ){
                $this->info('Backup File is not exist');
                continue;
            }

            if(!file_exists($originalCommand)){
                $this->info('Config File is not exist');
                continue;
            }

            copy($desCommand,$originalCommand,$stream_context);
            $this->info('Copied');

        }
    }
}
<?php

class Admin_CompanyController extends Custom_Controller_Action
{
    /**
     * @var App_Model_List_Original $originalClass
     */
    private $originalClass = 'App_Model_List_Original';

	public function init() {

    ini_set('memory_limit', '256M');
		parent::init();

    // $this->logger = Custom_Logger_Publish::getInstance();
    
		//権限チェック
		$profile = Custom_User_Admin::getInstance()->getProfile();
		if($profile->privilege_edit_flg != "1") {
			if($profile->privilege_manage_flg == "1") {
				$this->_redirect('/admin/account/');
			}else{
				$this->_redirect('/admin/password/');
			}
		}
	}

    /**
     * redirect to base when user does not have permission
     *
     */
    private function checkUserRules()
    {
        $user = Custom_User_Admin::getInstance();
        if ($user->isAgency() && !$this->checkPrivilegeEdit(Custom_User_Admin::getInstance()->getProfile()->id)) {
            $this->_redirect('/admin/company/');
        }
    }
    
	/**
	 * 一覧表示
	 */
    public function indexAction()
    {
    	// topicPath
    	$this->view->topicPath('契約管理');
		//オブジェクト取得
		$companyObj = App_Model_DbTable_Company::slave();
		$cmpAgreTypeObj = new App_Model_List_CompanyAgreementType();
		$this->view->company_agree_list = $cmpAgreTypeObj->getAll();
		$search_form = new Admin_Form_CompanySearch();
		$this->view->search_form = $search_form;
		$this->view->agency = Custom_User_Admin::getInstance()->isAgency() && !$this->checkPrivilegeEdit(Custom_User_Admin::getInstance()->getProfile()->id);

		if(!$this->getparam("cnt") || $this->getparam("cnt") == "" || !is_numeric($this->getparam("cnt")))  $this->getRequest()->setParam("cnt" ,20);
		if(!$this->getparam("page") || $this->getparam("page") == "" || !is_numeric($this->getparam("page")))  $this->getRequest()->setParam("page" ,1);

		//パラメータ取得
		$params = $this->_request->getParams();

		$select = $companyObj->select();
		$select->from(array("c" => "company"), array(new Zend_Db_Expr("SQL_CALC_FOUND_ROWS c.*")));
		$select->where("c.delete_flg = ?", 0);

		//検索時
		//登録完了時
		if($this->_hasParam("search_id") && $this->_getParam("search_id") != "" && is_numeric($this->_getParam("search_id"))) {
			$select->where("c.id = ?", $this->_getParam("search_id"));

		}else {

			if($search_form->isValid($params)) {

				//契約
				if($this->_hasParam("contract_type") && $this->_getParam("contract_type") != "") {
					$select->where("c.contract_type = ?", $this->_getParam("contract_type"));
				}

				//会員
				if($this->_hasParam("member_no") && $this->_getParam("member_no") != "") {
					$select->where("c.member_no like ? ", '%'.$this->_getParam("member_no"). '%');
				}

				//会社名
				if($this->_hasParam("company_name") && $this->_getParam("company_name") != "") {
					$select->where("c.company_name like ? ", '%'.$this->_getParam("company_name"). '%');
				}

				//利用開始日
				if(($this->_hasParam("start_date_s") && $this->_getParam("start_date_s") != "") && ($this->_hasParam("start_date_e") && $this->_getParam("start_date_e") != "")) {
					$select->where("c.start_date >= ?", $this->_getParam("start_date_s") ." 00:00:00");
					$select->where("c.start_date <= ?", $this->_getParam("start_date_e") ." 23:59:59");

				}else if(($this->_hasParam("start_date_s") && $this->_getParam("start_date_s") != "") && ($this->_hasParam("start_date_e") && $this->_getParam("start_date_e") == "")) {
					$select->where("c.start_date != '0000-00-00 00:00:00' && c.start_date >= ?", $this->_getParam("start_date_s") ." 00:00:00");

				}else if(($this->_hasParam("start_date_s") && $this->_getParam("start_date_s") == "") && ($this->_hasParam("start_date_e") && $this->_getParam("start_date_e") != "")) {
					$select->where("c.start_date != '0000-00-00 00:00:00' && c.start_date <= ?", $this->_getParam("start_date_e") ." 23:59:59");
				}

				//利用停止日
				if(($this->_hasParam("end_date_s") && $this->_getParam("end_date_s") != "") && ($this->_hasParam("end_date_e") && $this->_getParam("end_date_e") != "")) {
					$select->where("c.end_date >= ?", $this->_getParam("end_date_s") ." 00:00:00");
					$select->where("c.end_date <= ?", $this->_getParam("end_date_e") ." 23:59:59");

				}else if(($this->_hasParam("end_date_s") && $this->_getParam("end_date_s") != "") && ($this->_hasParam("end_date_e") && $this->_getParam("end_date_e") == "")) {
					$select->where("c.end_date != '0000-00-00 00:00:00' && c.end_date >= ?", $this->_getParam("end_date_s") ." 00:00:00");

				}else if(($this->_hasParam("end_date_s") && $this->_getParam("end_date_s") == "") && ($this->_hasParam("end_date_e") && $this->_getParam("end_date_e") != "")) {
					$select->where("c.end_date != '0000-00-00 00:00:00' && c.end_date <= ?", $this->_getParam("end_date_e") ." 23:59:59");
				}
			}
		}

		$search_form->populate($params);
		$select->order(array("c.id desc"));

		$select->limitPage($this->getparam("page"), $this->getparam("cnt"));
		//print($select->__toString());
		$rows = $companyObj->fetchAll($select);
		$this->view->company = $rows;

		// Paginatorのセットアップ
		$paginator = Zend_Paginator::factory($companyObj->getFoundRow($select));
		$paginator->setCurrentPageNumber($this->_getParam('page'));
		$paginator->setItemCountPerPage($this->_getparam("cnt"));
		$this->view->paginator = $paginator;

		$search_arr = array();
		foreach($search_form as $key => $val) {
			$search_arr[$key] = ($val->getValue() == null) ? "" :  $val->getValue();
		}

		$this->view->search_param = $search_arr;

    }

	/**
	 * 新規登録・編集表示
	 */
    public function editAction()
    {
        $this->checkUserRules();
        
		$this->view->topicPath('契約管理', "index", $this->getRequest()->getControllerName());
		$this->view->topicPath("契約者登録");

		//設定系の情報取得
		$company_config = new Zend_Config_Ini(APPLICATION_PATH ."/modules/admin/config/company.ini" , APPLICATION_ENV );
		//API系のURIなど
		$defailt_backbone = $company_config->backbone->api;
		$this->view->backbone = $defailt_backbone;
		//ＦＴＰ
		$this->view->default_ftp = $company_config->company->ftp;
		//コントロールパネル
		$this->view->default_cp  = $company_config->company->controlpanel;

		//パラメータ取得
		$params = $this->_request->getParams();
		
		//契約者情報の取得
		$companyObj = App_Model_DbTable_Company::slave();
		$row = array() ;
		if ( $this->_hasParam( 'id' ) && ( $params[ 'id' ] > 0 ) )
		{
			$row = $companyObj->getDataForId(	$params[	'id'	] ) ;
			if( $row == null )
			{
				throw new Exception( "No Company Data. " )	;
				exit	;
			}
		}
		
		//フォーム設定
		$this->view->form = $form = new Custom_Form();
		$form->assignSubForm(new Admin_Form_CompanyStatus( $row )		, 'status'		) ;
		$form->assignSubForm(new Admin_Form_BasicInfo()					, 'basic'		) ;
		$form->assignSubForm(new Admin_Form_ContractReserveInfo( $row )	, 'reserve'		) ;
		$form->assignSubForm(new Admin_Form_ContractCancelInfo()		, 'cancel'		) ;
		$form->assignSubForm(new Admin_Form_CompanyRegistControlPanel()	, 'cp'			) ;
		$form->assignSubForm(new Admin_Form_CompanyRegistCms()			, 'cms'			) ;
		$form->assignSubForm(new Admin_Form_CompanyRegistFtp()			, 'ftp'			) ;
		$form->assignSubForm(new Admin_Form_CompanyRegistOther()		, 'other'		) ;
		
		$cmpAgreTypeObj = new App_Model_List_CompanyAgreementType();
		$this->view->company_agree_list = $cmpAgreTypeObj->getAll();

		//オブジェクト取得
		$companyAccountObj = App_Model_DbTable_CompanyAccount::slave();

		//登録ボタン押下時
		if($this->_hasParam("asd") && $this->_getParam("asd") != "") {

			//契約が「評価・分析のみ契約」の場合は必須を外す
			if(isset($params['basic']['contract_type']) && $params['basic']['contract_type'] == App_Model_List_CompanyAgreementType::CONTRACT_TYPE_ANALYZE) {
				foreach($form as $name => $val) {
					foreach($form->$name as $key => $element) {
						if(!in_array($key, array("member_no","member_name","login_id","password"))) $element->setRequired(false);
					}
				}
			}

			//バリデーション
			if($form->isValid($params)) {

				$error_flg = false;

				//既に契約店Noがある場合
				$rows = $companyObj->getDataForMemberNo($params['basic']["member_no"], $params['basic']["id"]);
				if($rows != null && $rows->count() > 0) {
					$form->basic->getElement('member_no')->addErrors( array("既に加盟店Noが使用されています。") );
					$error_flg = true;
				}

				//CMSのログインIDが登録されている場合
				$rows = $companyAccountObj->getDataForLoginId($params['cms']["login_id"], $params['cms']["account_id"]);
				if($rows != null && $rows->count() > 0) {
					$form->cms->getElement('login_id')->addErrors( array("既にCMSログインIDが使用されています。") );
					$error_flg = true;
				}

				//ドメインチェック
				if($params['basic']["domain"] != "") {
					$rows = $companyObj->getDataForDomain($params['basic']["domain"], $params['basic']["id"]);
					if($rows != null && $rows->count() > 0) {
						$form->basic->getElement('domain')->addErrors( array("既にこのドメインは使用されています。") );
						$error_flg = true;
					}
				}

				//「評価・分析のみ契約」の場合
				if($params['basic']['contract_type'] == App_Model_List_CompanyAgreementType::CONTRACT_TYPE_ANALYZE) {
					//利用開始日が入って無いのに利用停止日が入っている場合はエラー
					if( ( isset( $params['basic']['reserve_start_date'] ) == false ) && isset( $params['basic']['end_date'] ) ) {
						$form->basic->getElement('end_date')->addErrors( array("利用開始日が設定されていない場合は、利用停止日を設定できません。") );
						$error_flg = true;

					//利用開始日が入っているのに利用停止日が入ってない場合はエラー
					}else if( isset( $params['basic']['reserve_start_date'] ) && ( isset( $params['basic']['end_date'] ) == false ) ) {
						$form->basic->getElement('end_date')->addErrors( array("既に本契約されている場合は、利用停止日を設定しなければなりません。") );
						$error_flg = true;
					}
				}

				//利用日チェック
				if( $params['reserve']['reserve_applied_start_date'] != "" && $params['reserve']['reserve_start_date'] != "" ) {
					$reserve_applied_start_date	= str_replace("-", "", $params['reserve']['reserve_applied_start_date'	] ) ;
					$reserve_start_date			= str_replace("-", "", $params['reserve']['reserve_start_date'			] ) ;
					if( $reserve_applied_start_date > $reserve_start_date ) {
						$form->reserve->getElement('reserve_applied_start_date')->addErrors( array("利用開始申請日は、利用開始日より過去日を設定してください。") );
						$error_flg = true;
					}
				}

				//利用日チェック
				if($params['cancel']['applied_end_date'] != "" && $params['cancel']['end_date'] != "") {
					$applied_end_date = str_replace("-", "", $params['cancel']['applied_end_date']);
					$end_date = str_replace("-", "", $params['cancel']['end_date']);
					if($applied_end_date > $end_date) {
						$form->cancel->getElement('applied_end_date')->addErrors( array("利用停止申請日は、利用停止日より過去日を設定してください。") );
						$error_flg = true;
					}
				}

				//利用開始日と利用停止日のチェック
				if( ( $params['reserve']['reserve_start_date'] != "" ) && ( $params['cancel']['end_date'] != "" ) ) {
					if ( $this->view->form->status->getContractSatus() != 'off' )
					{	// no validate at recontract
						$start	= str_replace( "-", "", $params[ 'reserve'	][ 'reserve_start_date'	] ) ;
						$end	= str_replace( "-", "", $params[ 'cancel'	][ 'end_date'			] ) ;
						if( $start > $end )
						{
							$form->cancel->getElement('end_date')->addErrors( array( "利用停止日は、利用開始日より未来日を設定してください。" ) ) ;
							$error_flg = true;
						}
					}
				}

				// 初回利用開始日と利用停止日のチェック
				if ( isset( $params[ 'status' ] ) && ( $params[ 'status' ][ 'initial_start_date' ] != "" ) && ( $params[ 'cancel' ][ 'end_date' ] != "" ) )
				{
					$start	= str_replace( "-", "", $params[ 'status' ][ 'initial_start_date'	] ) ;
					$end	= str_replace( "-", "", $params[ 'cancel' ][ 'end_date'				] ) ;
					if( $start > $end )
					{
						$form->cancel->getElement('end_date')->addErrors( array( "利用停止日は、初回利用開始日より未来日を設定してください。"	) ) ;
						$error_flg	= true ;
					}
				}
				
				//契約担当者系の設定
				if($params['reserve']['reserve_contract_staff_id'] != "" && ($params['reserve']['reserve_contract_staff_name'] == "" || $params['reserve']['reserve_contract_staff_department'] == "")) {
					$form->reserve->getElement('reserve_contract_staff_name')->addErrors( array("契約担当者名が設定されていません。参照ボタンより取得してください。") );
					$error_flg = true;
				}else if($params['reserve']['reserve_contract_staff_id'] == "" && ($params['reserve']['reserve_contract_staff_name'] != "" || $params['reserve']['reserve_contract_staff_department'] != "")) {
					$form->reserve->getElement('reserve_contract_staff_id')->addErrors( array("契約担当者が設定されていません。") );
					$error_flg = true;
				}

				//解約担当者系の設定
				if($params['cancel']['cancel_staff_id'] != "" && ($params['cancel']['cancel_staff_name'] == "" || $params['cancel']['cancel_staff_department'] == "")) {
					$form->cancel->getElement('cancel_staff_name')->addErrors( array("解約担当者名が設定されていません。参照ボタンより取得してください。") );
					$error_flg = true;
				}else if($params['cancel']['cancel_staff_id'] == "" && ($params['cancel']['cancel_staff_name'] != "" || $params['cancel']['cancel_staff_department'] != "")) {
					$form->cancel->getElement('cancel_staff_id')->addErrors( array("解約担当者が設定されていません。") );
					$error_flg = true;
				}
				
				// ATHOME_HP_DEV-2447 【プラン変更】地図検索の解約情報の未入力アラート
				if (
					( isset( $row[ 'map_start_date'	]		)														) &&
					( isset( $row[ 'map_end_date'	]		)														) &&
					( $row->map_start_date							  != ""											) &&
					( $row->map_end_date							  == ""											) &&
					( $params[ 'reserve'	][ 'reserve_cms_plan'	] == App_Model_List_CmsPlan::CMS_PLAN_ADVANCE	)
				) {
					$form->reserve->getElement( 'reserve_cms_plan'	)->addErrors( array( "「地図検索設定」の停止処理が完了してません。" ) );
					$error_flg = true ;
				}
				if (
					( isset( $row[ 'map_start_date'	]		)														) &&
					( isset( $row[ 'map_end_date'	]		)														) &&
					( $row->map_start_date							  != ""											) &&
					( $row->map_end_date							  == ""											) &&
					( $params[ 'cancel'	][ 'end_date'				] != ""											)
				) {
					$form->cancel->getElement( 'end_date'	)->addErrors( array( "「地図検索設定」の停止処理が完了してません。" ) );
					$error_flg = true ;
				}
				$secondEstateRow	= App_Model_DbTable_SecondEstate::slave()->getDataForCompanyId( $params[ 'id' ] ) ;
				if (
					( $secondEstateRow								  != null										) &&
					( $secondEstateRow	->start_date				  != ""											) &&
					( $secondEstateRow	->end_date					  == ""											) &&
					( $params[ 'cancel'	][ 'end_date'				] != ""											)
				) {
					$form->cancel->getElement( 'end_date'	)->addErrors( array( "「2次広告自動公開設定」の停止処理が完了してません。" ) );
					$error_flg = true ;
				}
					
				if(!$error_flg) {
					//submit削除
					$this->_setParam("asd", "");
					$this->_setParam("back", "");
					$this->_forward("conf");
					return;
				}
			}

			//チェックが終わったら、必須系を戻す（見た目が気持ち悪い感じになるので）
			foreach($form as $name => $val) {
				foreach($form->$name as $key => $element) {
					if(!in_array($key, array('applied_end_date', 'end_date', 'cancel_staff_id', 'cancel_staff_name', 'cancel_staff_department', 'remarks'))) $element->setRequired(true);
				}
			}

			$form->populate($params);

		//戻るボタン押下時
		}else if($this->_hasParam("back") && $this->_getParam("back") != "") {
			unset($params['back']);
			$form->populate($params);

		//確認画面でエラーになった場合
		}else if($this->_hasParam("conf_error") && $this->_getParam("conf_error") != "") {

			$form->populate($params);
			//エラー内容の設定
			foreach($this->_getParam("conf_error_str") as $name => $data) {
				foreach($data as $key => $val) {
					$form->$name->getElement($key)->addErrors( array($val) );
				}
			}
			unset($params['conf_error']);
			unset($params['conf_error_str']);

		//初期データ取得時
		}else if($this->_hasParam("id") && $this->_getParam("id") != "") {

			//契約者情報の取得
			$row = array();
			$row = $companyObj->getDataForId($this->_getParam("id"))->toArray();
			
			//  ATHOME_HP_DEV-2235	デモアカウントのHPコピー機能
			if ( array_key_exists( 'copy', $params ) && ( $params[ 'copy' ] == 'true' ) ) {
				$form->basic->contract_type	->setValue( App_Model_List_CompanyAgreementType::CONTRACT_TYPE_DEMO	) ;
				$form->basic->copy_from_member_no->setValue(	$row[ 'member_no'	] ) ;
				$form->cp->cp_url->setValue(					$row[ 'cp_url'		] ) ;
				$row = array()				;
			} else {
				$form->basic->removeElement( 'copy_from_member_no' ) ;
				//アカウントの取得
				$rows = array() ;
				$rowsObj = $companyAccountObj->getDataForCompanyId( $this->_getParam( "id" ) ) ;
				foreach( $rowsObj as $key => $val ) {
					$rows = $val->toArray() ;
					break ;
				}
				if( isset( $rows ) && count( $rows ) > 0 ) {
					if( !isset( $rows[ 'account_id' ] ) ) {
						$rows[ 'account_id' ] = $rows[ 'id' ] ;
						unset( $rows['id'] ) ;
					}
					$form->populate( $rows ) ;
				}
			}

			if($row != null) {
				//日付周りの調整
				$date = substr( $row[ 'applied_start_date'			], 0, 10 ) ;
				$row[ 'applied_start_date'			] = ( ( $date == "0000-00-00" ) ? "" : $date	) ;
				
				$date = substr( $row[ 'start_date'					], 0, 10 ) ;
				$row[ 'start_date'					] = ( ( $date == "0000-00-00" ) ? "" : $date	) ;
				
				$date = substr( $row[ 'initial_start_date'			], 0, 10 ) ;
				$row[ 'initial_start_date'			] = ( ( $date == "0000-00-00" ) ? "" : $date	) ;
				
				$date = substr( $row[ 'reserve_applied_start_date'	], 0, 10 ) ;
				$row[ 'reserve_applied_start_date'	] = ( ( $date == "0000-00-00" ) ? "" : $date	) ;
				
				$date = substr( $row[ 'reserve_start_date'			], 0, 10 ) ;
				$row[ 'reserve_start_date'			] = ( ( $date == "0000-00-00" ) ? "" : $date	) ;
				
				$date = substr( $row[ 'applied_end_date'			], 0, 10 ) ;
				$row[ 'applied_end_date'			] = ( ( $date == "0000-00-00" ) ? "" : $date	) ;
				
				$date = substr( $row[ 'end_date'					], 0, 10 ) ;
				$row[ 'end_date'					] = ( ( $date == "0000-00-00" ) ? "" : $date	) ;
				
				$date = substr( $row[ 'map_applied_start_date'		], 0, 10 ) ;
				$row[ 'map_applied_start_date'		] = ( ( $date == "0000-00-00" ) ? "" : $date	) ;
				
				$date = substr( $row[ 'map_start_date'				], 0, 10 ) ;
				$row[ 'map_start_date'				] = ( ( $date == "0000-00-00" ) ? "" : $date	) ;
				
				$date = substr( $row[ 'map_applied_end_date'		], 0, 10 ) ;
				$row[ 'map_applied_end_date'		] = ( ( $date == "0000-00-00" ) ? "" : $date	) ;
				
				$date = substr( $row[ 'map_end_date'				], 0, 10 ) ;
				$row[ 'map_end_date'				] = ( ( $date == "0000-00-00" ) ? "" : $date	) ;
			}

			$form->populate($row);

		}else{

			//デフォルト値を設定していく
			$form->basic->contract_type->setValue(0);
			//ＦＴＰ
			$defailt_ftp = $company_config->company->ftp;
			$form->ftp->ftp_server_port->setValue($defailt_ftp->port);
//			$form->ftp->ftp_pasv_flg->setValue(0);
			$form->ftp->ftp_server_name->setValue($defailt_ftp->server_name);
			$form->ftp->ftp_password->setValue($defailt_ftp->password);
			//コントロールパネル
			$defailt_cp = $company_config->company->controlpanel;
			$form->cp->cp_url->setValue($defailt_cp->url);
		}

		$this->view->assign("params", $params);

    }

	/**
	 * 新規登録・編集確認表示
	 */
    public function confAction() {

        $this->checkUserRules();
        
    	$this->view->topicPath('契約管理', "index", $this->getRequest()->getControllerName());
    	$this->view->topicPath("契約者登録確認");

		$companyObj = new App_Model_DbTable_Company();
		$companyAccountObj = new App_Model_DbTable_CompanyAccount();

		//契約内容
		$cmpAgreTypeObj = new App_Model_List_CompanyAgreementType();
		$this->view->company_agree_list = $cmpAgreTypeObj->getAll();

		// プラン
		$cmpCmsPlanObj	= new App_Model_List_CmsPlan() ;
		$this->view->cms_plan_list = $cmpCmsPlanObj->getAll() ;
		
		//PASVモード
		$ftpPasvObj = new App_Model_List_FtpPasvMode();
		$this->view->pasv = $ftpPasvObj->getAll();

		//パラメータ取得
		$params = $this->_request->getParams();

		//元に戻るボタン押下時
		if($this->_hasParam("back") && $this->_getParam("back") != "") {
			$this->_forward("edit");
			return;
		}
		
		if( $this->_hasParam("submit_regist") && $this->_getParam("submit_regist") != "" )
		{	//登録ボタン押下時
			$conf_error_str = array();
			//再度チェック（既に契約店Noがある場合）
			$rows = $companyObj->getDataForMemberNo($params['basic']["member_no"], $params['basic']["id"]);
			if($rows != null && $rows->count() > 0) {
				$this->_setParam("conf_error", "true");
				$conf_error_str = array_merge(array("basic" => array("member_no" => "既に加盟店Noが使用されています。")), $conf_error_str);
			}

			//再度チェック（CMSのログインIDが登録されている場合）
			$rows = $companyAccountObj->getDataForLoginId($params['cms']["login_id"], $params['cms']["account_id"]);
			if($rows != null && $rows->count() > 0) {
				$this->_setParam("conf_error", "true");
				$conf_error_str =  array_merge(array("cms" => array("login_id" => "既にCMSログインIDが使用されています。")),$conf_error_str);
			}

			if($this->_hasParam("conf_error") && $this->_getParam("conf_error") != "") {
				$this->_setParam("conf_error_str", $conf_error_str);
				$this->_forward("edit");
				return;
			}

			$tableCompany = App_Model_DbTable_Company::master();
			$tableAccount = App_Model_DbTable_CompanyAccount::master();

			$adapter = $tableCompany->getAdapter();
			$adapter->beginTransaction();

			//契約者登録
			$copy_from_member_no	= ( isset( $params['basic']["copy_from_member_no"] ) ? $params['basic']["copy_from_member_no"] : null	) ;
			unset($params["module"]);
			unset($params["controller"]);
			unset($params["action"]);
			unset($params["submit"]);
			unset($params['status']["contract_status"	]	) ;
			unset($params['status']["cms_plan"			]	) ;
			unset($params['basic']["copy_from_member_no"]	) ;

			//新規
			if( !isset($params['basic']["id"]) || $params['basic']["id"] == "" || $copy_from_member_no ) {

				unset($params["id"]);

				$create_arr = array();
				foreach($params as $name => $arr) {
					if( $name == "cms"				) continue ;
					if( $name == "submit_regist"	) continue ;
					
					foreach($arr as $key => $val) {
						if ( $key == "id" ) continue	;
						if ( $key == "initial_start_date"			&& $val == "" ) { $val = new Zend_Db_Expr( "NULL" ) ; }
						if ( $key == "reserve_applied_start_date"	&& $val == "" ) { $val = new Zend_Db_Expr( "NULL" ) ; }
						if ( $key == "start_date"					&& $val == "" ) { $val = new Zend_Db_Expr( "NULL" ) ; }
						if ( $key == "applied_start_date"			&& $val == "" ) { $val = new Zend_Db_Expr( "NULL" ) ; }
						if ( $key == "reserve_start_date"			&& $val == "" ) { $val = new Zend_Db_Expr( "NULL" ) ; }
						if ( $key == "applied_end_date"				&& $val == "" ) { $val = new Zend_Db_Expr( "NULL" ) ; }
						if ( $key == "end_date"						&& $val == "" ) { $val = new Zend_Db_Expr( "NULL" ) ; }
						if ( $key == "map_applied_start_date"		&& $val == "" ) { $val = new Zend_Db_Expr( "NULL" ) ; }
						if ( $key == "map_start_date"				&& $val == "" ) { $val = new Zend_Db_Expr( "NULL" ) ; }
						if ( $key == "map_applied_end_date"			&& $val == "" ) { $val = new Zend_Db_Expr( "NULL" ) ; }
						if ( $key == "map_end_date"					&& $val == "" ) { $val = new Zend_Db_Expr( "NULL" ) ; }
						if ( $key == "ftp_pasv_flg"					&& $val == "" ) { $val = new Zend_Db_Expr( "NULL" ) ; }
						if ( $key == "ftp_server_port"				&& $val == "" ) { $val = new Zend_Db_Expr( "NULL" ) ; }
						$create_arr[$key] = $val;
					}
				}

				$row = $tableCompany->createRow($create_arr);
				$id = $row->save();
				$this->_setPlan( $params, $tableCompany, $id, $copy_from_member_no )	;
				
				//  ATHOME_HP_DEV-2235	デモアカウントのHPコピー機能
				if ( $copy_from_member_no ) {
					$table		= App_Model_DbTable_Company::master()	;
					$profile	= $table->fetchLoginProfileByMemberNo( $copy_from_member_no )	;
					$currentHp	= $profile->getCurrentHp()	;
					$newHp		= $currentHp->copyAll()		;
					$table		= App_Model_DbTable_AssociatedCompanyHp::master()	;
					$data					  = array()		;
					$data[ 'company_id'		] = $id			;
					$data[ 'current_hp_id'	] = $newHp->id	;
					$table->insert( $data )	;
				}

				//アカウント登録
				$row = $tableAccount->createRow();
				$row->company_id = $id;
				$row->login_id   = $params['cms']["login_id"];
				$row->password   = $params['cms']["password"];
				$row->save();
				
				if ( $params[ 'basic'][ 'contract_type' ] == App_Model_List_CompanyAgreementType::CONTRACT_TYPE_PRIME )
				{	// 新規の本契約なら、そのまま2次広告自動公開設定へ
					$adapter->commit()	;
                    if ($tableCompany->getDataForId($id)->cms_plan == App_Model_List_CmsPlan::CMS_PLAN_ADVANCE) {
                        $this->_redirect('admin/company/detail/?id='. $id);
                    }
                    if($params[ 'reserve'][ 'reserve_cms_plan' ] == App_Model_List_CmsPlan::CMS_PLAN_LITE){
                        $this->_redirect('/admin/company/comp/id/'. $id);
                    }
					$this->_redirect(	"/admin/company/second-estate?company_id={$id}"	) ;
				}
				
			//更新
			}else{

				$row = $tableCompany->getDataForId($params['basic']["id"]);
				if($row == null) {
					throw new Exception("No Company Data.");
					return;
				}

				unset($row->delete_flg);
				unset($row->create_id);
				unset($row->create_date);
				unset($row->update_date);

				// ドメインが変更された場合は、全上げフラグを立てる
				if($params['basic']["domain"] != "" && !empty($row->domain) &&
						$row->domain != $params['basic']["domain"]) {
					// hp更新
					$hpTable = App_Model_DbTable_Hp::master();
					$reserveTable = App_Model_DbTable_ReleaseSchedule::master();
				
					$hp = array();
					if ($currentRows = $row->getCurrentHp()) {
						$hp[] = $currentRows;
					}
					foreach ($hp as $hprow) {
						$hpTable->update(array('all_upload_flg' => 1), array('id = ?' => $hprow->id));
						$reserveTable->update(array('delete_flg' => 1), array('hp_id = ?' => $hprow->id));
					}
				}

				//契約者更新
				$no_update_arr = array("id","delete_flg","create_id","create_date","update_id","update_date");

				foreach($params as $name => $arr) {
					if ( $name	== "cms"				) continue ;
					if ( $name	== "submit_regist"		) continue ;
					foreach($arr as $key => $val) {
						if ( $key == "id" 				) continue	;
						if ( $key == "member_linkno"	) continue	;
						if ( $key == "initial_start_date"			&& $val == "" ) { $val = new Zend_Db_Expr( "NULL" ) ; }
						if ( $key == "reserve_applied_start_date"	&& $val == "" ) { $val = new Zend_Db_Expr( "NULL" ) ; }
						if ( $key == "start_date"					&& $val == "" ) { $val = new Zend_Db_Expr( "NULL" ) ; }
						if ( $key == "applied_start_date"			&& $val == "" ) { $val = new Zend_Db_Expr( "NULL" ) ; }
						if ( $key == "reserve_start_date"			&& $val == "" ) { $val = new Zend_Db_Expr( "NULL" ) ; }
						if ( $key == "applied_end_date"				&& $val == "" ) { $val = new Zend_Db_Expr( "NULL" ) ; }
						if ( $key == "end_date"						&& $val == "" ) { $val = new Zend_Db_Expr( "NULL" ) ; }
						if ( $key == "map_applied_start_date"		&& $val == "" ) { $val = new Zend_Db_Expr( "NULL" ) ; }
						if ( $key == "map_start_date"				&& $val == "" ) { $val = new Zend_Db_Expr( "NULL" ) ; }
						if ( $key == "map_applied_end_date"			&& $val == "" ) { $val = new Zend_Db_Expr( "NULL" ) ; }
						if ( $key == "map_end_date"					&& $val == "" ) { $val = new Zend_Db_Expr( "NULL" ) ; }
						if ( $key == "ftp_pasv_flg"					&& $val == "" ) { $val = new Zend_Db_Expr( "NULL" ) ; }
						if ( $key == "ftp_server_port"				&& $val == "" ) { $val = new Zend_Db_Expr( "NULL" ) ; }
						$row->$key = $val;
					}
				}

				//check top original before
				$checkTopBefore = false;
				if($row != null && $row->cms_plan == App_Model_List_CmsPlan::CMS_PLAN_ADVANCE){
				    $checkTopBefore = $row->checkTopOriginal();
                }
                Zend_Registry::set('checkTopBefore',$checkTopBefore);

				$this->_setAutoCancel( $row )	;
				$id = $row->save();
				$this->_setPlan( $params, $tableCompany, $id )	;
				
				//アカウント更新
				$row = $tableAccount->getDataForId($params['cms']["account_id"]);
				if($row == null) {
					$adapter->rollback();
					throw new Exception("No CompanyAccount Data.");
					return;
				}
				$row->login_id   = $params['cms']["login_id"];
				$row->password   = $params['cms']["password"];
				$row->save();
			}
			
			$adapter->commit();
			
            if ($tableCompany->getDataForId($id)->cms_plan == App_Model_List_CmsPlan::CMS_PLAN_ADVANCE) {
                $this->_redirect('admin/company/detail/?id='. $id);
            } else {
                 $this->_redirect('/admin/company/comp/id/'. $id);
            }

		}else{

			//評価分析のときは登録しない
			if(isset($params['basic']["id"]) && $params['basic']["id"] == "" && 
			   isset($params['basic']['contract_type']) && $params['basic']['contract_type'] == App_Model_List_CompanyAgreementType::CONTRACT_TYPE_ANALYZE) {

				$this->view->form->ftp->ftp_server_name->setValue("");
				$this->view->form->ftp->ftp_server_port->setValue("");
				$this->view->form->ftp->ftp_user_id->setValue("");
				$this->view->form->ftp->ftp_password->setValue("");
				$this->view->form->ftp->ftp_directory->setValue("");
				$this->view->form->cp->cp_url->setValue("");
			}
		}

		$this->view->assign("params", $params);
	}

    /**
     * @param App_Model_DbTable_Company_Row $row
     * @throws Exception
     */
	protected function _setAutoCancel( App_Model_DbTable_Company_Row& $row )
	{
		// ATHOME_HP_DEV-2592 【管理画面】既存会員がスタンダード変更後の地図検索画面に値が入っている／NHP-3003
		if (
			( $row->cms_plan			==  App_Model_List_CmsPlan::CMS_PLAN_ADVANCE 	) &&
			( $row->reserve_cms_plan	==  App_Model_List_CmsPlan::CMS_PLAN_STANDARD 	)
		) {
			$row->map_applied_start_date		= null										;
			$row->map_start_date				= null										;
			$row->map_contract_staff_id			= null										;
			$row->map_contract_staff_department	= null										;
			$row->map_contract_staff_department	= null										;
			$row->map_applied_end_date			= null										;
			$row->map_end_date					= null										;
			$row->map_cancel_staff_id			= null										;
			$row->map_cancel_staff_name			= null										;
			$row->map_cancel_staff_department	= null										;
			$row->map_remarks					= null										;
		}
		
		// ATHOME_HP_DEV-2452 【プラン変更】契約情報予約の自動入力について
		if (
			( $row->cms_plan			==  App_Model_List_CmsPlan::CMS_PLAN_STANDARD 	) &&
			( $row->reserve_cms_plan	==  App_Model_List_CmsPlan::CMS_PLAN_ADVANCE 	) &&
			( $row->map_start_date		!=  null 										) &&
			( $row->map_end_date		==  null										)
		) {
			$before_reserve_start_date			= strftime( '%Y-%m-%d', strtotime( '-1 day', strtotime( $row->reserve_start_date ) ) ) ;
			$row->map_applied_end_date			= $row->reserve_applied_start_date			;
			$row->map_end_date					= $before_reserve_start_date				;
			$row->map_cancel_staff_id			= $row->reserve_contract_staff_id			;
			$row->map_cancel_staff_name			= $row->reserve_contract_staff_name			;
			$row->map_cancel_staff_department	= $row->reserve_contract_staff_department	;
		}
        // ATHOME_HP_DEV-3039 地図検索設定の自動入力について
        if (
			( $row->cms_plan			==  App_Model_List_CmsPlan::CMS_PLAN_STANDARD 	) &&
			( $row->reserve_cms_plan	==  App_Model_List_CmsPlan::CMS_PLAN_LITE 	    ) &&
			( $row->map_start_date		!=  null 										) &&
			( $row->map_end_date		==  null										)
		) {
			$before_reserve_start_date			= strftime( '%Y-%m-%d', strtotime( '-1 day', strtotime( $row->reserve_start_date ) ) ) ;
			$row->map_applied_end_date			= $row->reserve_applied_start_date			;
			$row->map_end_date					= $before_reserve_start_date				;
			$row->map_cancel_staff_id			= $row->reserve_contract_staff_id			;
			$row->map_cancel_staff_name			= $row->reserve_contract_staff_name			;
			$row->map_cancel_staff_department	= $row->reserve_contract_staff_department	;
		}
			
		// ↓ATHOME_HP_DEV-2446 【プラン変更】解約情報にて「利用停止申請日」「 利用停止日」「 解約担当者」が入力された場合の自動入力機能
		$ser	= App_Model_DbTable_SecondEstate::master()->fetchRow( [ 'company_id = ?' => $row->id ] ) ;
		if ( $ser != null )
		{
            // ATHOME_HP_DEV-3039 2次広告自動公開設定の自動入力について
            if (
                ( $row->cms_plan			>=  App_Model_List_CmsPlan::CMS_PLAN_STANDARD 	) &&
                ( $row->reserve_cms_plan	==  App_Model_List_CmsPlan::CMS_PLAN_LITE 	    )  &&
                ( $ser->start_date  	    !=  null 										)  &&
                ( $ser->end_date		    ==  null										)
            ) {
                $before_reserve_start_date			= strftime( '%Y-%m-%d', strtotime( '-1 day', strtotime( $row->reserve_start_date ) ) ) ;
                $ser->applied_end_date				= $row->reserve_applied_start_date			    ;
				$ser->end_date						= $before_reserve_start_date					;
				$ser->cancel_staff_id				= $row->reserve_contract_staff_id				;
				$ser->cancel_staff_name				= $row->reserve_contract_staff_name			    ;
                $ser->cancel_staff_department		= $row->reserve_contract_staff_department		;
            }
			if (
				( $row->cms_plan			!=  App_Model_List_CmsPlan::CMS_PLAN_NONE 		) &&
				( $ser->start_date			!=  null 										) &&
				( $ser->end_date			==  null										)
			) {
				$ser->applied_end_date				= $row->applied_end_date			;
				$ser->end_date						= $row->end_date					;
				$ser->cancel_staff_id				= $row->cancel_staff_id				;
				$ser->cancel_staff_name				= $row->cancel_staff_name			;
				$ser->cancel_staff_department		= $row->cancel_staff_department		;
			}
			
			if (
				( $row->cms_plan			==  App_Model_List_CmsPlan::CMS_PLAN_STANDARD 	) &&
				( $row->map_start_date		!=  null 										) &&
				( $row->map_end_date		==  null										)
			) {
				$row->map_applied_end_date			= $row->applied_end_date			;
				$row->map_end_date					= $row->end_date					;
				$row->map_cancel_staff_id			= $row->cancel_staff_id			;
				$row->map_cancel_staff_name			= $row->cancel_staff_name			;
				$row->map_cancel_staff_department	= $row->cancel_staff_department		;
			}
			$ser->save()	;
		}

		//ATHOME_HP_DEV-3826
		App_Model_List_Original::_setAutoCancel($row);
	}
	
	protected function _setPlan( &$params, &$tableCompany, $id, $copy_from_member_no = null )
	{
		$row = $tableCompany->getDataForId( $id ) ;
		$startDate	= strtotime( $params[ 'reserve' ][ 'reserve_start_date' ] ) ;
		if ( ( $startDate !== false ) && ( $startDate < time() ) )
		{	// 予約の利用開始日が当日以前なら即時反映
			$hpId				= $row->getCurrentHp()->id			;
			$changer			= new Custom_Plan_ChangeCms()		;
			$allowUpdateCheck	= !( $copy_from_member_no )			;
			if ( $hpId && $allowUpdateCheck )	// デモコピーだと現在の契約情報を強制的に変更
			{
				$changer->changePlan( $hpId,	$row ) ;
			}
			else
			{
				$changer->updatePlanInfo( $row, false ) ;
			}
		}
	}

	/**
	 * 新規登録・編集完了表示
	 */
    public function compAction() {

    	$this->view->topicPath('契約管理', "index", $this->getRequest()->getControllerName());
    	$this->view->topicPath("契約者登録完了");

		//パラメータ取得
		$params = $this->_request->getParams();
		$this->view->assign("params", $params);

		//オブジェクト取得
		$companyObj = App_Model_DbTable_Company::slave();
		//契約者情報の取得
		$row = $companyObj->getDataForId($this->_getParam("id"));
		if($row == null) {
			throw new Exception("No Company Data. ");
			exit;
		}
		$this->view->contract_type		= $row[ "contract_type"			] ;
		$this->view->reserve_cms_plan	= $row[ "reserve_cms_plan"		] ;
		$this->view->cms_plan			= $row[ "cms_plan"				] ;
        $company_id = $this->_getParam("id");
        $isAdmin = Custom_User_Admin::getInstance()->checkIsSuperAdmin(Custom_User_Admin::getInstance()->getProfile());
        $isAgency = Custom_User_Admin::getInstance()->isAgency();

        if ($row->checkTopOriginal() && !$this->checkRedirectTopOriginal($row, $company_id, $isAdmin, $isAgency)) {
            $this->view->original_plan = true;
        }
        $this->view->original_setting_title = App_Model_List_Original::getOriginalSettingTitle();
        $this->view->original_edit_title = App_Model_List_Original::getOriginalEditTitle();
        $this->view->original_tag = App_Model_List_Original::getEffectMeasurementTitle();
    }
  
  
  /*
   * return directory working for manage file and folder upload
   *
   * @return class Custom_DirectoryIterator
   */
  private function getCurrentDirectory()
  {
    // get request params
    $params = $this->_request->getParams();
    $company_id = isset($params['company_id']) ? $params['company_id'] : '';
    $sub_dir = isset($params['sub_dir']) ? $params['sub_dir'] : '';
    
    if('' == $company_id || !is_numeric($company_id)) {
      return;
    }
    
    $rootDir = App_Model_List_Original::getOriginalImportPath($company_id);
    $rootRedirect = App_Model_List_Original::getOriginalImportUrl($company_id);
    $dataInfo = App_Model_List_Original::getOriginalImportDataInfo($company_id);
    
    $uploadDir = $rootDir;
    $redirectTo = $rootRedirect;
    
    $isSubDir =  array_key_exists($sub_dir, $dataInfo);
    if ($isSubDir) {
      $uploadDir = $dataInfo[$sub_dir]['direction'];
      $redirectTo = $dataInfo[$sub_dir]['link'];
    }
    
    $di = new Custom_DirectoryIterator(true);

    // initial folders
    $di->initialImportHtmlDir($company_id);
    
    $di->load($uploadDir);
    $di->setRootUrl($rootRedirect);
    
    // if ($sub_dir === App_Model_List_Original::ORIGINAL_IMPORT_TOPKOMA) {
        // $company = App_Model_DbTable_Company::slave()->getDataForId($company_id);
        // $hp = $company->getCurrentHp();
        // $setting = $hp->getEstateSetting(App_Model_DbTable_HpEstateSetting::SETTING_FOR_PUBLIC);
        // // $specials = $setting->getSpecialAllWithPubStatus('id');
        // $specials = $setting->getSpecialAll('id');
        // $di->mergeSpecialFiles($specials);
    // }
    
    if ($uploadDir == $rootDir) {
      $data = $dataInfo[App_Model_List_Original::ORIGINAL_IMPORT_TOPROOT];

      $di->setExtensions($data['accepted_exts']);
      $di->setSpecialFiles($data['accepted_files'], $data['extra_files']);
    
      foreach ($dataInfo as $key => $info) {
        $di->setDataFile($key, $info);
      }
    } else if($isSubDir) {  
      $data = $dataInfo[$sub_dir];    
      
      $di->setExtensions($data['accepted_exts']);
      $di->ignoreRootDir(false);
      if (isset($data['accepted_files']) && count($data['accepted_files']) > 0) {
        $di->setSpecialFiles($data['accepted_files'], $data['extra_files']);
      }
      $di->setData($data);
    }
    
    return $di;
  }
  
  /**
   * Read content of file
   *
   * @return json_encode $res 
   */
  public function apiGetFileContentAction()
  {
    $di = $this->getCurrentDirectory();

    $originalFile = $this->getparam('original_file') ?: '';
    if ($originalFile) {
      $res = $di->readFileContent($originalFile);
      return $this->_helper->json($res);
    }
    
    return $this->_helper->json(array('status'=> 0, 'errors'=> 'Invalid params.'));
  }
    
  /**
   * Api save file action
   *
   * @return json_encode || void
   */
  public function apiSaveFileAction()
  {
    $di = $this->getCurrentDirectory();
    $sub_dir = $this->_hasParam('sub_dir') ? $this->getparam('sub_dir') : '';
    $originalFile = $this->getparam('original_file') ?: '';
    $res = array('success' => 1, 'data' => ['Ok' => true]);
    
    if ($originalFile && $this->_hasParam('change_name')) {
        $fileName = $this->getparam('change_name');
        if (false == $di->updateFileName($originalFile, $fileName)) {
            
            if ('' != $di->getMessageError()) {
                $res['success'] = 0;
                return $this->_helper->json($res);
            }

            $errors = [
                'DUPLICATE' => 'フォルダ内に同じ名前のファイルが存在しています。変更してください。',
                App_Model_List_Original::ORIGINAL_IMPORT_TOPCSS => 'このフォルダには、.cssファイルをアップロードしてください。',
                App_Model_List_Original::ORIGINAL_IMPORT_TOPJS => 'このフォルダには、.jsファイルをアップロードしてください。',
                App_Model_List_Original::ORIGINAL_IMPORT_TOPIMAGE => 'このフォルダには、画像形式のファイルをアップロードしてください。',
            ];
                    
            if (false == $di->checkIsValidName($fileName)) {
                // $res['data']['error'] ='Invalid file name.';
            } else if (false == $di->checkIsAllowExtension($fileName)) {
                if ('' != $sub_dir && isset($errors[$sub_dir])) {
                    $res['data']['error'] = $errors[$sub_dir];
                }
            } else if ($di->checkIsExistFile($fileName)) {
                $res['data']['error'] = $errors['DUPLICATE'];
            }
        }
        
        return $this->_helper->json($res);
    }
    
    if ($this->_hasParam('revert_content')) {
        if (false == $di->revertFileContent($this->getparam('revert_content'))) {
            if ('' != $di->getMessageError()) {
                $res['success'] = 0;
                return $this->_helper->json($res);
            }
            
        }
        return $this->_helper->json($res);
    }
    
    if ($this->_hasParam('remove_content')) {
      if (false == $di->removeFile($this->getparam('remove_content'))) {
        if ('' != $di->getMessageError()) {
            $res['success'] = 0;
            return $this->_helper->json($res);
        }
            
      }
      return $this->_helper->json($res);
    }
    
    if ($originalFile && $this->_hasParam('change_content')) {
      if (false == $di->updateFileContent($originalFile, $this->getparam('change_content'))) {
        if ('' != $di->getMessageError()) {
            $res['success'] = 0;
            return $this->_helper->json($res);
        }
            
      }
      return $this->_helper->json($res);
    }
    
    return $this->_helper->json(array('status'=> 0, 'errors'=> 'Invalid params.'));
  }
  
    /**
   * function turn on flag to force stop progress
   *
   */
  public function apiSynchronizeUploadProgressAction()
  {
    $params = $this->_request->getParams();
    $isSuccess = isset($params['isSuccess']) ? (int)$params['isSuccess'] : 0;
    
    $di = $this->getCurrentDirectory();

    if (1 === $isSuccess) {
        $di->mergeDir();
        $data = array('success'=> 1, 'data'=> 'Ok');
        return $this->_helper->json($data);
    }
    $di->removeTmpDir();
    $data = array('success'=> 2, 'data'=> 'Ok');
    return $this->_helper->json($data);
  }
  
  /**
   * Manage files upload
   *
   * @return json_encode || void
   */
  public function apiUploadFileAction()
  {
    $this->_helper->layout->disableLayout();
    $this->_helper->viewRenderer->setNoRender();
	
	$errors = [];
    if ($this->getRequest()->isPost()) {
        $di = $this->getCurrentDirectory();
        $di->removeTmpDir(); // remove old tmp files
        
        $upload = new Zend_File_Transfer_Adapter_Http();
        $upload->setDestination($di->getTmpDestination())
          // ->addValidator('Size', false, 8388608)
          // ->addValidator('Filessize', false, 8388608)
          ->addValidator('Extension', false, $di->getExtensions());

        $files  = $upload->getFileInfo();
        foreach ($files as $file => $fileInfo) {
			// $msg = '';
            if ($upload->isUploaded($file)) {
				if ($upload->isValid($file) && $di->checkIsValidFile($fileInfo['name'])) {
					// if ($di->checkIsSpecialFile($fileInfo['name'])) $di->backupFile($fileInfo['name']);
					if ($upload->receive($file)) {
						$info = $upload->getFileInfo($file);
						$tmp  = $info[$file]['tmp_name'];
						// $data[] = $tmp;
					}
				}
            }
			
			// if ('' !== $msg) {
				// $errors[] = $msg;
			// }
        }
    }
    
    $data = array(
        'success'=> 1,
        'message'=> 'Ok',
        'errors'=> $errors,
    );
    return $this->_helper->json($data);
  }
  
  /**
   * 09_List File Edit
   * 09_契約管理:編集ファイル一覧
   * Manage files upload
   *
   * @return json_encode || void
   */
  public function topListFileEditAction()
  {
    $originalClass = $this->originalClass;
    $di = $this->getCurrentDirectory();
    
    $params = $this->_request->getParams();
    $company_id = isset($params['company_id']) ? $params['company_id'] : '';

    if ('' == $company_id) {
        throw new Exception( "No Company Data. " );
        exit;
    }

    $row = $this->_checkCompanyTOP($company_id);
    $isAdmin = Custom_User_Admin::getInstance()->checkIsSuperAdmin(Custom_User_Admin::getInstance()->getProfile());
    $isAgency = Custom_User_Admin::getInstance()->isAgency();

    if ($this->checkRedirectTopOriginal($row, $company_id, $isAdmin, $isAgency)) {
        $this->_redirect('/admin/company/detail?id='.$company_id);
        exit;
    }

    $sub_dir = isset($params['sub_dir']) ? $params['sub_dir'] : '';

    $sortByDate = isset($params['field']) && $params['field'] == 'date';
    $sortOrderBy = isset($params['orderby']) && $params['orderby'] == 'desc';

    $data = new stdClass();
    $data->nlist      = $sortByDate ? $di->getListByDate($sortOrderBy) : $di->getList($sortOrderBy);
    $data->sub_dir    = $sub_dir;
    $data->company_id = $company_id;
    
    foreach ($data->nlist as $item) {
      $item->hasContextMenu = false;
      if ($item->isFile && ($item->data['can_edit_name'] || $item->data['can_edit_data'])) $item->hasContextMenu = true;
      $item->isSpecialFile = $di->checkIsSpecialFile($item->name);
    }



    $params['backTOP'] = $originalClass::getScreenUrl($originalClass::ORIGINAL_EDIT_CMS,$company_id);
    $params['backCurrent'] = $originalClass::getScreenUrl($originalClass::ORIGINAL_EDIT_FILE,$company_id);
    $params['isRoot'] = (isset($params['sub_dir']) && $params['sub_dir'] == App_Model_List_Original::ORIGINAL_IMPORT_TOPROOT )
      || !isset($params['sub_dir']) ? true : false;

    $sub_dir_param = (isset($params['sub_dir']) && $params['sub_dir'] == App_Model_List_Original::ORIGINAL_IMPORT_TOPROOT )
    || !isset($params['sub_dir']) ? '' : '&sub_dir=' . $params['sub_dir'];
    $params['currentUrl'] = $params['backCurrent'].$sub_dir_param ;

    $params['warnings'] = $this->text->getMultiKeyValue(array(
        'list_file_edit.warning_html',
        'list_file_edit.warning_2',
        'list_file_edit.warning_3',
    ),'list_file_edit.warning_title_change_key');

    $view_title = $originalClass::getScreenTitle($originalClass::ORIGINAL_EDIT_FILE);
    if(isset($params['sub_dir'])){
        $params['warnings'] = array();
        switch($params['sub_dir']){
            case App_Model_List_Original::ORIGINAL_IMPORT_TOPCSS:
                $params['warnings'] = $this->text->getMultiKeyValue(array(
                    'list_file_edit.warning_css',
                    'list_file_edit.warning_2',
                    'list_file_edit.warning_3',
                ),'list_file_edit.warning_title_change_key');
                $view_title = 'top_cssフォルダ';
                break;
            case App_Model_List_Original::ORIGINAL_IMPORT_TOPJS:
                $params['warnings'] = $this->text->getMultiKeyValue(array(
                    'list_file_edit.warning_js',
                    'list_file_edit.warning_2',
                    'list_file_edit.warning_3',
                ),'list_file_edit.warning_title_change_key');
                $view_title = 'top_jsフォルダ';
                break;
            case App_Model_List_Original::ORIGINAL_IMPORT_TOPIMAGE:
                $params['warnings'] = $this->text->getMultiKeyValue(array(
                    'list_file_edit.warning_image',
                    'list_file_edit.warning_2',
                    'list_file_edit.warning_3',
                ),'list_file_edit.warning_title_change_key');
                $view_title = 'top_imageフォルダ';
                break;
            case App_Model_List_Original::ORIGINAL_IMPORT_TOPKOMA:
                $params['warnings'] = $this->text->getMultiKeyValue(array(
                    'list_file_edit.warning.koma_1',
                    'list_file_edit.warning.koma_2',
                    'list_file_edit.warning.koma_3',
                ),'list_file_edit.warning_title_change_key');
                $view_title = 'bukken_komaフォルダ';
                break;
            default:
                //
        }
    }

    $this->breadcrumbTOPEdit($view_title, $company_id);
    
    $this->view->assign('data', $data);
    $this->view->assign('params',$params);
  }
   
	/**
	 * 詳細表示
	 */
    public function detailAction()
    {

    	$this->view->topicPath('契約管理', "index", $this->getRequest()->getControllerName());
    	$this->view->topicPath("契約者詳細");

    	//オブジェクト取得
    	$companyObj = App_Model_DbTable_Company::slave();
    	$companyAccountObj = App_Model_DbTable_CompanyAccount::slave();
    	
    	//契約者情報の取得
    	$row = $companyObj->getDataForId( $this->_getParam( "id" ) ) ;
    	if( $row == null )
    	{
    		throw new Exception( "No Company Data. " )	;
    		exit	;
    	}
		 
        $company_id = $this->_getParam("id");
        $isAdmin = Custom_User_Admin::getInstance()->checkIsSuperAdmin(Custom_User_Admin::getInstance()->getProfile());
        $isAgency = Custom_User_Admin::getInstance()->isAgency();
        $isEdit = Custom_User_Admin::getInstance()->getProfile()->privilege_edit_flg;
        $isManage = Custom_User_Admin::getInstance()->getProfile()->privilege_manage_flg;

        if ($row->checkTopOriginal() && !$this->checkRedirectTopOriginal($row, $company_id, $isAdmin, $isAgency)) {
            $this->view->original_plan = true;
        } else if ($row->checkTopOriginal() && ($isEdit || ($isEdit && $isManage))) {
            $this->view->original_edit = true;
        }
        $this->view->original_setting_title = App_Model_List_Original::getOriginalSettingTitle();
        $this->view->original_edit_title = App_Model_List_Original::getOriginalEditTitle();
        $this->view->original_tag = App_Model_List_Original::getEffectMeasurementTitle();
 		$this->view->current_hp	= $row->getCurrentHp()		;			// HPがあるかどうかの判断の為
        $this->view->agency = $isAgency && !$this->checkPrivilegeEdit(Custom_User_Admin::getInstance()->getProfile()->id);
    	
    	//フォーム設定
		$this->view->form = $form = new Custom_Form();
		$form->assignSubForm(new Admin_Form_CompanyStatus( $row )		, 'status'		) ;
		$form->assignSubForm(new Admin_Form_BasicInfo()					, 'basic'		) ;
		$form->assignSubForm(new Admin_Form_ContractReserveInfo()		, 'reserve'		) ;
		$form->assignSubForm(new Admin_Form_ContractCancelInfo()		, 'cancel'		) ;
		$form->assignSubForm(new Admin_Form_CompanyRegistControlPanel()	, 'cp'			) ;
		$form->assignSubForm(new Admin_Form_CompanyRegistCms()			, 'cms'			) ;
		$form->assignSubForm(new Admin_Form_CompanyRegistFtp()			, 'ftp'			) ;
		$form->assignSubForm(new Admin_Form_CompanyRegistOther()		, 'other'		) ;
		
		//契約内容
		$cmpAgreTypeObj = new App_Model_List_CompanyAgreementType();
		$this->view->company_agree_list = $cmpAgreTypeObj->getAll();

		// プラン
		$cmpCmsPlanObj	= new App_Model_List_CmsPlan() ;
		$this->view->cms_plan_list = $cmpCmsPlanObj->getAll() ;
		
		//PASVモード
		$ftpPasvObj = new App_Model_List_FtpPasvMode();
		$this->view->pasv = $ftpPasvObj->getAll();

		//日付周りの設定
		$row->initial_start_date			= $row->initial_start_date_view			;
		$row->reserve_applied_start_date	= $row->reserve_applied_start_date_view	;
		$row->reserve_start_date			= $row->reserve_start_date_view			;
		$row->start_date					= $row->start_date_view					;
		$row->applied_start_date			= $row->applied_start_date_view			;
		$row->applied_end_date				= $row->applied_end_date_view			;
		$row->end_date						= $row->end_date_view					;
		$row->map_applied_start_date		= $row->map_applied_start_date_view		;
		$row->map_start_date				= $row->map_start_date_view				;
		$row->map_applied_end_date			= $row->map_applied_end_date_view		;
		$row->map_end_date					= $row->map_end_date_view				;
		$row = $row->toArray();

		//インターネットコードの設定  @TODO
		$row['member_linkno'] = $this->getInternetCode($row['member_no']);

		$form->populate($row);

		//アカウントの取得
		$rowsObj = $companyAccountObj->getDataForCompanyId($row['id']);
		if($rowsObj == null) {
			throw new Exception("No Company Account Data. ");
			exit;
		}
		$rows = array();
		foreach($rowsObj as $key => $val) {
			$rows = $val->toArray();
			break;
		}

		if(isset($rows) && count($rows) > 0) {
			if(!isset($rows['account_id'])) {
				$rows['account_id'] = $rows['id'];
				unset($rows['id']);
			}
			$form->populate($rows);
		}
	}

	/**
	 * 会員APIに接続して会員番号に対応するインターネットコードを取得します。
	 */
	private function getInternetCode($member_no) {

	    // 会員番号が設定されていない場合は何も返さない
	    if (empty($member_no)) return null;

        // 会員APIに接続して会員情報を取得
        $apiParam = new Custom_Kaiin_Kaiin_KaiinParams();
        $apiParam->setKaiinNo($member_no);
        $apiObj = new Custom_Kaiin_Kaiin_Kaiin();
        $kaiinData = $apiObj->get($apiParam, '会員基本取得');
        if (is_null($kaiinData) || empty($kaiinData)) {
            return "会員Noに誤りがあります。";
        }

        $kaiinData = (object)$kaiinData;
        if (!property_exists($kaiinData,'kaiinLinkNo') || empty($kaiinData->kaiinLinkNo)){
            return "インターネットコードが設定されていません。";
        }
        return $kaiinData->kaiinLinkNo;
	}

	/**
	 * CSV出力用
	 */
    public function csvAction()
    {

		$companyObj = App_Model_DbTable_Company::slave();
		$select = $companyObj->select();
		$select->where("delete_flg = 0");
		$select->order("");
		$rows = $companyObj->fetchAll($select);
		$rows_arr = $rows->toArray();

		$accountObj   = App_Model_DbTable_CompanyAccount::slave();
		$assCompHpObj = App_Model_DbTable_AssociatedCompanyHp::slave();
		$hpObj        = App_Model_DbTable_HpPage::slave();
		$logDelObj    = App_Model_DbTable_LogDelete::slave();
		$secondEstateObj	= App_Model_DbTable_SecondEstate::slave()	;
        $originalSettingObj = App_Model_DbTable_OriginalSetting::slave();

		//CSV対象カラム名
		$csv_header = App_Model_List_CompanyCsvDownloadHeader::getCsvHeader();

		//CSV表示カラム名
		$csv_header_name = App_Model_List_CompanyCsvDownloadHeader::getCsvHeaderName();

		// 出力
		$fileName = "keiyaku.csv";
		header("Pragma: public");
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename=' . $fileName);

	    $stream = fopen('php://output', 'w');
		$csv_row_name = array();
		foreach($csv_header_name as $name) {
			mb_convert_variables('SJIS-win', 'UTF-8', $name);
			$csv_row_name[] = $name;
		}

		fputcsv($stream, $csv_row_name);

		$agree_anmes = App_Model_List_CompanyAgreementType::getInstance()->getAll();

        $ids = $accountRows = $assCompHpRows = $secondEstateRows = $originalSettingRows = [];

        foreach($rows_arr as $key => $val) {
            $ids[] = $val['id'];
        }

        if(!empty($ids)) {

            // build select
            $select = array('company_id IN (?)' => $ids);

            // get account rows
            $accountRowsData = $accountObj->fetchAll($select);
            if($accountRowsData) {
                foreach ($accountRowsData as $k => $v) {
                    if(!isset($accountRows[$v->company_id])){
                        $accountRows[$v->company_id] = [$v];
                        continue;
                    }
                    $accountRows[$v->company_id][] = $v;
                }
            }

            // get associate company
            $assCompHpRowsData = $assCompHpObj->fetchAll($select);
            if($assCompHpRowsData) {
                foreach ($assCompHpRowsData as $k => $v) {
                    $assCompHpRows[$v->company_id] = $v;
                }
            }

            // get second estate
            $secondEstateRowsData = $secondEstateObj->fetchAll($select);
            if($secondEstateRowsData) {
                foreach ($secondEstateRowsData as $k => $v) {
                    $secondEstateRows[$v->company_id] = $v;
                }
            }

            // get original setting
            $originalSettingRowsData = $originalSettingObj->fetchAll($select);
            if($originalSettingRowsData){
                foreach ($originalSettingRowsData as $k => $v){
                    $originalSettingRows[$v->company_id] = $v;
                }
            }
        }


		$csvs = array();
		foreach($rows_arr as $key => $val) {

		    $companyId = $val['id'];
            //CMSのログイン情報を取得する
            $accountRow = isset($accountRows[$companyId]) ? $accountRows[$companyId] : null ;

            //HP_PAGEの状況を確認する
            $assCompHpRow = isset($assCompHpRows[$companyId]) ? $assCompHpRows[$companyId] : null ;

            // ２次広告の状況を確認する
            $secondEstateRow	= isset($secondEstateRows[$companyId]) ? $secondEstateRows[$companyId] : null ;

            $originalSettingRow = isset($originalSettingRows[$companyId]) ? $originalSettingRows[$companyId] : null ;


            //やはり全部出す
			//if($val['contract_type'] != App_Model_List_CompanyAgreementType::CONTRACT_TYPE_PRIME) continue;

			//CMSのログイン情報を取得する
//			$accountRow = $accountObj->getDataForCompanyId($val['id']);
//
//			//HP_PAGEの状況を確認する
//			$assCompHpRow = $assCompHpObj->fetchRowByCompanyId($val['id']);
//
//			// ２次広告の状況を確認する
//			$secondEstateRow	= $secondEstateObj->getDataForCompanyId(	$val[ 'id' ] ) ;
//
//            $originalSettingRow = $originalSettingObj->getDataForCompanyId($val['id']);

			$csv_row = array();
			foreach($csv_header as $name) {
				if( in_array( $name, array( 'reserve_applied_start_date','reserve_start_date','applied_end_date','end_date', 'initial_start_date', 'applied_start_date', 'start_date', 'map_applied_start_date', 'map_start_date', 'map_applied_end_date', 'map_end_date' ) ) ) {
					$date_view_name = $name ."_view";
					$val[$name] = $rows[$key]->$date_view_name;

				}else if($name == "contract_type") {
					$val[$name] = $agree_anmes[$val['contract_type']];
				}
				
				if ( ( $secondEstateRow !== null ) && ( strpos( $name, 'second_estate_') === 0 ) )
				{	// 「second_estate_」から始まっている場合
					$columnName		= substr( $name, 14 )	;
					$val[ $name ]	= $secondEstateRow->$columnName	;
				}
				
                if (($originalSettingRow !== null) && (strpos($name, 'original_setting_') === 0)) {
                    $columnName = str_replace('original_setting_', '', $name);
                    $val[$name] = $originalSettingRow->$columnName;
                }

				if ( $name == 'contract_status' )
				{
					$value = $rows[ $key ]->isAvailable()																?  1	: 2			;
					$value = $rows[ $key ]->contract_type == App_Model_List_CompanyAgreementType::CONTRACT_TYPE_ANALYZE	? '-'	: $value	;
					$val[ $name ] = $value	;
				}
				
				if ( ( $name == "cms_plan" ) || ( $name == "reserve_cms_plan" ) )
				{
					switch ( $val[ $name ] )
					{
						case App_Model_List_CmsPlan::CMS_PLAN_ADVANCE	: $value =	1 ; break ;
						case App_Model_List_CmsPlan::CMS_PLAN_STANDARD	: $value =	2 ; break ;
                        case App_Model_List_CmsPlan::CMS_PLAN_LITE      : $value =	3 ; break ;
						default											: $value = '' ; break ;
					}
					$val[ $name ] = $value	;
				}

				mb_convert_variables('SJIS-win', 'UTF-8', $val[$name]);
				if($name == "ftp_password" || $name == "cp_password") {
					$csv_row[] = (string)$rows[$key]->$name;

				//CMSのログインID設定
				}else if($name == "cms_id") {
					$csv_row[] = (string)$accountRow[0]->login_id;

				//CMSのログインパスワード設定
				}else if($name == "cms_password") {
					$csv_row[] = (string)$accountRow[0]->password;

				//最終更新日を設定
				}else if($name == "release_date") {

					//まだ作成していない
					if($assCompHpRow == null) {
						$csv_row[] = '';
						continue;
					}

					$select = $hpObj->select();
					$select->where("hp_id = ?", $assCompHpRow->current_hp_id);
					$select->where("public_flg = 1");
					$select->order("published_at DESC");
					//print($select->__toString());
					$pubRow = $hpObj->fetchRow($select);
					if($pubRow == null) {
						$csv_row[] = '';
					}else{
						$csv_row[] = $pubRow->published_at;
					}

				//最終更新停止日を設定
				}else if($name == "published_stop_date") {

					$delRow = $logDelObj->getLastDeleteForComapnyId($val['id']);
					if($delRow == null) {
						$csv_row[] = '';
					}else{
						$csv_row[] = $delRow->datetime;
					}

				}else{
					$csv_row[] = (string)$val[$name];
				}


			}
			fputcsv($stream, $csv_row);
		}
		fclose($stream);
		exit;
	}

	/**
	 * PDF出力用
	 */
    public function pdfAction()
    {

		if(!$this->_hasParam("id") || $this->getparam("id") == "" || !is_numeric($this->getparam("id"))) {
			throw new Exception("No Company	 ID. ");
			exit;
		}

		//パラメータ取得
		$params = $this->_request->getParams();

		//オブジェクト取得
		$companyObj = App_Model_DbTable_Company::slave();

		//契約店情報の取得
		$company_row = $companyObj->getDataForId($this->_getParam("id"));
		if($company_row == null) {
			throw new Exception("No Company Data. ");
			exit;
		}
		$this->view->company = $company_row;
		$this->view->mapOption	= ( $company_row->map_start_date ) && !( $company_row->map_end_date ) ? true : false ;
		
		//CMSログインアカウント情報の取得
		$companyAccountObj = App_Model_DbTable_CompanyAccount::slave();
		$rowsObj = $companyAccountObj->getDataForCompanyId($this->_getParam("id"));
		$this->view->account = $rowsObj[0];

		//Googleアカウント情報の取得
		$companyAccountObj = App_Model_DbTable_CompanyAccount::slave();
		$rowsObj = $companyAccountObj->getDataForCompanyId($this->_getParam("id"));
		$this->view->account = $rowsObj[0];


		$tagObj = App_Model_DbTable_Tag::slave();
		$row = $tagObj->getDataForCompanyId($this->getparam("id"));
		$this->view->google = $row;

		$secondEstateTable = App_Model_DbTable_SecondEstate::slave();
		$row = $secondEstateTable->getDataForCompanyId($this->getparam("id"));
            /*
				利用する表示
				　　開始日が設定（過去・未来問わず）and 利用停止日がブランク
				
				　　利用しない表示
				　　　　開始日がブランク or 利用停止日が設定（過去・未来問わず）
             */
		$isUse = !is_null($row) && ($row->start_date) && !($row->end_date) ? true : false;
		$secondEstate = new stdClass;
		if ($isUse){
			$secondEstate->isUse = '利用する'; 
			$prefCodes = json_decode($row->area_search_filter)->area_1;
			$prefs = App_Model_Estate_PrefCodeList::getInstance()->pick($prefCodes);
			$secondEstate->area = implode(' ', $prefs); 
		} else {
			$secondEstate->isUse = '利用しない'; 
		    $secondEstate->area = '－'; 
		}
		
		$this->view->secondEstate = $secondEstate;

        $originalSettingTable = App_Model_DbTable_OriginalSetting::slave();
        $rowOriginal = $originalSettingTable->getDataForCompanyId($this->getparam("id"));
        $datetime = new DateTime();
        $datetime->setTimeZone(new DateTimeZone('Asia/Tokyo'));
        $today = $datetime->format('Ymd');

        $isUseOriginal = !is_null($rowOriginal) && ($rowOriginal->start_date) && (!$rowOriginal->end_date || strtotime($rowOriginal->end_date) > strtotime($today)) ? true : false;

        $originalSetting->plan = true;
        if ($isUseOriginal) {
            $originalSetting->isUse = '利用する';
        } else {
            $originalSetting->isUse = '利用しない';
        }

        $this->view->originalSetting = $originalSetting;

		//何使おうかしら
		//http://codezine.jp/article/detail/7141

		// KaiinSummaryApi用パラメータ作成
		$kaiin_no = $company_row['member_no'];
		$apiParam = new Custom_Kaiin_KaiinSummary_KaiinSummaryParams();
		$apiParam->setKaiinNo($kaiin_no);
		// 会員APIに接続して会員情報を取得
		$apiObj = new Custom_Kaiin_KaiinSummary_GetKaiinSummary();
		$kaiinDetail = (object) $apiObj->get($apiParam, '会員概要取得');
		
		if (isset($kaiinDetail->mainTantoCd)) {
			// TantoApi用パラメータ作成
			$tantoApiParam = new Custom_Kaiin_Tanto_TantoParams();
			$tantoApiParam->setTantoCd($kaiinDetail->mainTantoCd);
			//会員APIに接続して担当者情報を取得
			$tantoapiObj = new Custom_Kaiin_Tanto_GetTanto();
			$tantouInfo = (object) $tantoapiObj->get($tantoApiParam, '担当者取得');
			if (isset($tantouInfo->tantoShozoku["mShozokuKaName"])) {
				$shozokuka = $tantouInfo->tantoShozoku["mShozokuKaName"];
				$this->view->shozokuka = $shozokuka;
			} else {
				throw new Exception("No Shozokuka Data. ");
				exit;
			}
		}else{
			throw new Exception("No KaiinSummary Data. ");
			exit;	
		}

		include("mpdf60/mpdf.php");
		$html = $this->view->render("company/pdf.phtml");
/*
		'ja', //モード default ''
		'A4', //用紙サイズ default''
		0,    //フォントサイズ default 0
		'',   //フォントファミリー
		15,   //左マージン
		15,   //右マージン
		2,    //トップマージン
		2,    //ボトムマージン
		0,    //ヘッダーマージン
		0,   //フッターマージン
		''    //L-landscape,P-portrait
*/
		$mpdf = new mPDF('ja+aCJK', 'A4', 8, 'メイリオ', 10, 10, 5, 5, 0, 0, '');
		$mpdf->mirrorMargins = 0;
		$mpdf->WriteHTML($html);
		$data  = $mpdf->Output("", "S");


		$ua = $_SERVER['HTTP_USER_AGENT'];
		$file_name = $company_row->company_name .'_開通通知書.pdf';
        $file_name_encoded = $file_name;

		if (strstr($ua, 'Trident') || strstr($ua, 'MSIE')) {
            $file_name_encoded = mb_convert_encoding($file_name, "SJIS-win","UTF-8");
		}

		// PDFを出力します
		header('Content-Type: application/pdf');
		// downloaded.pdf という名前で保存させます
		header('Content-Disposition: attachment; filename="'. $file_name_encoded .'"; filename*=UTF-8\'ja\'' . rawurlencode($file_name));
		echo $data;
		exit;
	}

	/**
	 * 非公開設定
	 */
    public function privateAction()
    {
        $this->checkUserRules();
        
		if(!$this->_hasParam("company_id") || $this->getparam("company_id") == "" || !is_numeric($this->getparam("company_id"))) {
			throw new Exception("No Company ID. ");
			exit;
		}

		//オブジェクト取得
		$companyObj = new App_Model_DbTable_Company();
		$row = $companyObj->getDataForId($this->_getParam("company_id"));
		if($row == null) {
			throw new Exception("No Company Data. ");
			exit;
		}
		$this->view->company = $row;

		//パラメータ取得
		$params = $this->_request->getParams();
		$this->view->assign("params", $params);

		//パンクズ設定
    	$this->view->topicPath('契約管理', "index", $this->getRequest()->getControllerName());
		$pan_arr = array("id" => $this->_getParam("company_id"));
    	$this->view->topicPath("契約者詳細", "detail", $this->getRequest()->getControllerName(), $pan_arr);
    	$this->view->topicPath("非公開設定");


		//FTPしにいけるかのちぇっく
    	$assoc = App_Model_DbTable_AssociatedCompanyHp::slave();
    	$assocRow = $assoc->fetchRow(array('company_id = ?' => $this->_getParam("company_id")));

		$ftp_flg = true;
		//FTP情報があるか？
		if($row->ftp_server_name == "" || $row->ftp_user_id == "" || $row->ftp_password == "") {
			$ftp_flg = false;

		}else if($assocRow == null) {
			$ftp_flg = false;

		//HPを作成始めているか？
		}else if($row->getCurrentHp() == false){
			$ftp_flg = false;
		}

		$this->view->assign("ftp_flg", $ftp_flg);

		//FTP繋いで、HTMLを消しに行く
		if($this->_hasParam("del_flg") || $this->getparam("del_flg") != "" ) {

			$adapter = $companyObj->getAdapter();
			$adapter->beginTransaction();
			//ログを残す
			$ldObj = App_Model_DbTable_LogDelete::master();

			try {
				$item = array();
				$profile = Custom_User_Admin::getInstance()->getProfile();
				$item["manager_id"] = $profile->id;
				$item["hp_id"]      = $assocRow->current_hp_id;
				$item["company_id"] = $this->_getParam("company_id");
				$item["datetime"]   = date("Y-m-d H:i:s");
				$ldObj->insert($item);
			}catch(Exception $e) {
				$adapter->rollback();
				throw $e;
			}

			try {
				//HTMLをガシガシ消しに行く
				$ftp = new Custom_Ftp($row->ftp_server_name);

				//ログインする
				$ftp->login($row->ftp_user_id, $row->ftp_password);

				//パッシブモードの設定
				if($row->ftp_pasv_flg == App_Model_List_FtpPasvMode::IN_FORCE) $ftp->pasv(true);

				//HTMLが置かれているディレクトリに移動
				$ftp->chdir($row->ftp_directory);

				$list = $ftp->rawlist("./");
				foreach($list as $key => $val) {

					$child = preg_split("/\s+/", $val);

					if($child[8] == "." || $child[8] == "..") continue;

					if($child[0]{0} === "d") {
						$ftp->rmdir($child[8]);
					}else{
						$ftp->delete($child[8]);
					}
				}

			}catch(Exception $e) {
				$adapter->rollback();
				throw $e;
			}

            try {
                $row->deletePublicSpecial();
            } catch (Exception $e) {
                $adapter->rollback();
                throw $e;
            }

			$adapter->commit();

            $this->updateFlg($row);
            $this->_redirect('/admin/company/private-cmp/company_id/'. $this->getparam("company_id"));
			return;
		}
	}

    /**
     * ページのフラグ更新
     * - 全ページ非公開
     * - 全上げフラグ ON
     *
     * @param $companyRow
     */
    private function updateFlg($companyRow) {

        $table = App_Model_DbTable_HpPage::master();
        $adapter = $table->getAdapter();
        $adapter->beginTransaction();

        $hpTable = App_Model_DbTable_Hp::master();
        $reserveTable = App_Model_DbTable_ReleaseSchedule::master();

        $hp = array();
        if ($row = $companyRow->getCurrentHp()) {
            $hp[] = $row;
        }

        //// 代行作成サイトはいらんかった
        // if ($row = $companyRow->getCurrentCreatorHp()) {
        //     $hp[] = $row;
        // }

        foreach ($hp as $row) {
            $hpTable->update(array('all_upload_flg' => 1), array('id = ?' => $row->id));
            $table->update(array('public_flg' => 0, 'public_path' => NULL), array('hp_id = ?' => $row->id));
            $reserveTable->update(array('delete_flg' => 1), array('hp_id = ?' => $row->id));
        }
        $adapter->commit();
    }

	/**
	 * 非公開設定
	 */
    public function privateCmpAction()
    {

		//パラメータ取得
		$params = $this->_request->getParams();
		$this->view->assign("params", $params);

		//パンクズ設定
    	$this->view->topicPath('契約管理', "index", $this->getRequest()->getControllerName());
		$pan_arr = array("id" => $this->_getParam("company_id"));
    	$this->view->topicPath("契約者詳細", "detail", $this->getRequest()->getControllerName(), $pan_arr);
    	$this->view->topicPath("非公開設定");

	}

	/**
	 * タグ設定用
	 */
    public function tagAction()
    {
        $this->checkUserRules();
        
		if(!$this->_hasParam("company_id") || $this->getparam("company_id") == "" || !is_numeric($this->getparam("company_id"))) {
			throw new Exception("No Company ID. ");
			exit;
		}

		//オブジェクト取得
        $company_id = $this->_getParam("company_id");
        $controller = $this->getRequest()->getControllerName();
		$companyObj = new App_Model_DbTable_Company();
		$row = $companyObj->getDataForId($company_id);
		if($row == null) {
			throw new Exception("No Company Data. ");
			exit;
		}
		$this->view->company = $row;

		//パラメータ取得
		$params = $this->_request->getParams();

        $this->view->original_tag = $original_tag = App_Model_List_Original::getEffectMeasurementTitle();

		//パンクズ設定
    	$this->view->topicPath('契約管理', "index", $controller);
    	$this->view->topicPath("契約者詳細", "detail", $controller, array("id" => $company_id));
    	$this->view->topicPath($original_tag);

		$tagObj = App_Model_DbTable_Tag::slave();

		//フォーム設定
		$this->view->form = $form = new Custom_Form();
		$form->assignSubForm(new Admin_Form_CompanyGoogleAnalyticsTag(), 'google');
		// $form->assignSubForm(new Admin_Form_CompanyTag(), 'other');

		if($this->_hasParam("submit") && $this->getparam("submit") != "") {


			//契約が「評価・分析のみ契約」の場合は必須を外す
			if(isset($params['google']['id']) && $params['google']['id'] != "") {
				foreach($form->google as $key => $element) {
					if($key == "file_name") $element->setRequired(false);
				}
			}

			//バリデーション
			if($form->isValid($params)) {
				//submit削除
				$this->_setParam("submit", "");
				$this->_setParam("back", "");
				$this->_forward("tag-cnf");
				return;
			}

		}else if($this->_hasParam("back") || $this->getparam("back")) {
			unset($params['back']);
			$form->populate($params);

		} else {

			$row = $tagObj->getDataForCompanyId($this->getparam("company_id"));
			if($row != null || $row != false) {
				$row_data = $row->toArray();
				$form->populate($row_data);
			}
		}
		$form->populate($params);
		$this->view->assign("params", $params);
	}

	/**
	 * タグ設定用（確認）
	 */
    public function tagCnfAction()
    {
        $this->checkUserRules();
        
		if(!$this->_hasParam("company_id") || $this->getparam("company_id") == "" || !is_numeric($this->getparam("company_id"))) {
			throw new Exception("No Company	 ID. ");
			exit;
		}

		//オブジェクト取得
        $company_id = $this->_getParam("company_id");
        $controller = $this->getRequest()->getControllerName();
		$companyObj = App_Model_DbTable_Company::slave();
		$row = $companyObj->getDataForId($company_id);
		if($row == null) {
			throw new Exception("No Company Data. ");
			exit;
		}

        $this->view->original_tag = $original_tag = App_Model_List_Original::getEffectMeasurementTitle();

		//パンクズ設定
    	$this->view->topicPath('契約管理', "index", $controller);
    	$this->view->topicPath("契約者詳細", "detail", $controller, array("id" => $company_id));
    	$this->view->topicPath($original_tag);

		//パラメータ取得
		$params = $this->_request->getParams();

		//マスターで取る
		$tagObj = App_Model_DbTable_Tag::master();

		//フォーム設定
//		$this->view->form = $form = new Custom_Form();
//		$form->assignSubForm(new Admin_Form_CompanyGoogleAnalyticsTag(), 'google');
//		$form->assignSubForm(new Admin_Form_CompanyTag(), 'other');


		if($this->_hasParam("submit") && $this->getparam("submit") != "") {

			$data = array();
			$data['company_id']      = $params['company_id'];
			$data['google_user_id']  = $params['google']['google_user_id'];
			$data['google_password'] = $params['google']['google_password'];

			//URLとかを取得
			if($params['google']['file_name'] != "") {
				$conf = new Zend_Config_Ini(APPLICATION_PATH .'/modules/admin/config/FileUploadServer.ini', APPLICATION_ENV);
				$p12_data = @file_get_contents($conf->upload->admin_url . $params['company_id']  ."/google/". $params['google']['file_name']);
				if($p12_data === false) {
					throw new Exception("No File Error");
					exit;
				}
				$data['google_p12'] = $p12_data;
			}

			$data['google_analytics_mail']    = $params['google']['google_analytics_mail'];
			$data['google_analytics_view_id'] = $params['google']['google_analytics_view_id'];
			$data['google_analytics_code']    = $params['google']['google_analytics_code'];

			// $data['above_close_head_tag']  = $params['other']['above_close_head_tag'];
			// $data['under_body_tag']        = $params['other']['under_body_tag'];
			// $data['above_close_body_tag']  = $params['other']['above_close_body_tag'];

		
			$table   = App_Model_DbTable_HpPage::master();
			$adapter = $table->getAdapter();
			try {
			    $adapter->beginTransaction();
		
		    	//更新
			    if(isset($params['google']['id']) && $params['google']['id'] != "" && is_numeric($params['google']['id'])) {

				    $where = array("id = ?" => $params['google']['id']);
				    $tagObj->update($data, $where);

			    //新規
			    }else{
				    $id = $tagObj->insert($data);
			    }
			    
			    $adapter->commit();
		    } catch (Exception $e) {
			    $adapter->rollback();
			    throw $e;
		    }

			$this->_redirect('/admin/company/tag-cmp/company_id/'. $this->getparam("company_id"));
			return;

		}else if($this->_hasParam("back") && $this->getparam("back") != "") {
			$this->_forward("tag");
			return;
		}

		$this->view->assign("params", $params);
	}

	/**
	 * タグ設定用（完了）
	 */
    public function tagCmpAction()
    {

		//パラメータ取得
		$params = $this->_request->getParams();
		$this->view->assign("params", $params);

		$company_id = $this->_getParam("company_id");
		$companyObj = App_Model_DbTable_Company::slave();
		$row = $companyObj->getDataForId($company_id);

        if($row == null) {
            throw new Exception("No Company Data. ");
            exit;
        }

        $this->view->original_tag = $original_tag = App_Model_List_Original::getEffectMeasurementTitle();

		//パンクズ設定
    	$this->view->topicPath('契約管理', "index", $this->getRequest()->getControllerName());
		$pan_arr = array("id" => $this->_getParam("company_id"));
    	$this->view->topicPath("契約者詳細", "detail", $this->getRequest()->getControllerName(), $pan_arr);
    	$this->view->topicPath($original_tag);
	}
	/**
	 * その他タグ設定用
	 */
    public function otherTagAction()
    {
        $this->checkUserRules();
        
		if(!$this->_hasParam("company_id") || $this->getparam("company_id") == "" || !is_numeric($this->getparam("company_id"))) {
			throw new Exception("No Company ID. ");
			exit;
		}

		//オブジェクト取得
		$companyObj = new App_Model_DbTable_Company();
		$row = $companyObj->getDataForId($this->_getParam("company_id"));
		if($row == null) {
			throw new Exception("No Company Data. ");
			exit;
		}
		$this->view->company = $row;

		//パラメータ取得
		$params = $this->_request->getParams();

        $this->view->original_tag = $original_tag = App_Model_List_Original::getEffectMeasurementTitle();

		//パンクズ設定
    	$this->view->topicPath('契約管理', "index", $this->getRequest()->getControllerName());
		$pan_arr = array("id" => $this->_getParam("company_id"));
    	$this->view->topicPath("契約者詳細", "detail", $this->getRequest()->getControllerName(), $pan_arr);
    	$this->view->topicPath($original_tag);

		$tagObj = App_Model_DbTable_Tag::slave();

		//フォーム設定
		$this->view->form = $form = new Custom_Form();
		$form->assignSubForm(new Admin_Form_CompanyTag(), 'other');

		if($this->_hasParam("submit") && $this->getparam("submit") != "") {

			//バリデーション
			if($form->isValid($params)) {
				//submit削除
				$this->_setParam("submit", "");
				$this->_setParam("back", "");
				$this->_forward("other-tag-cnf");
				return;
			}

		}else if($this->_hasParam("back") || $this->getparam("back")) {
			unset($params['back']);
			$form->populate($params);

		} else {

			$row = $tagObj->getDataForCompanyId($this->getparam("company_id"));
			if($row != null || $row != false) {
				$row_data = $row->toArray();
				$form->populate($row_data);
			}
		}
		$form->populate($params);
		$this->view->assign("params", $params);
	}

	/**
	 * タグ設定用（確認）
	 */
    public function otherTagCnfAction()
    {
        $this->checkUserRules();
        
		if(!$this->_hasParam("company_id") || $this->getparam("company_id") == "" || !is_numeric($this->getparam("company_id"))) {
			throw new Exception("No Company	 ID. ");
			exit;
		}

		//オブジェクト取得
		$companyObj = App_Model_DbTable_Company::slave();
		$row = $companyObj->getDataForId($this->_getParam("company_id"));
		if($row == null) {
			throw new Exception("No Company Data. ");
			exit;
		}

        $this->view->original_tag = $original_tag = App_Model_List_Original::getEffectMeasurementTitle();

		//パンクズ設定
    	$this->view->topicPath('契約管理', "index", $this->getRequest()->getControllerName());
		$pan_arr = array("id" => $this->_getParam("company_id"));
    	$this->view->topicPath("契約者詳細", "detail", $this->getRequest()->getControllerName(), $pan_arr);
    	$this->view->topicPath($original_tag);


		//パラメータ取得
		$params = $this->_request->getParams();

		//マスターで取る
		$tagObj = App_Model_DbTable_Tag::master();

		if($this->_hasParam("submit") && $this->getparam("submit") != "") {

			$data = array();
			$FormObj = new Admin_Form_CompanyTag();
			foreach($FormObj as $key => $value){
				$data[$key]  = $params['other'][$key];
			}

			$table   = App_Model_DbTable_HpPage::master();
			$adapter = $table->getAdapter();
			try {
				$adapter->beginTransaction();
			
				//更新
			    if(isset($params['other']['id']) && $params['other']['id'] != "" && is_numeric($params['other']['id'])) {

				    $where = array("id = ?" => $params['other']['id']);
				    $tagObj->update($data, $where);

			    //新規
			    }else{
				    $id = $tagObj->insert($data);
			    }
				
				$adapter->commit();
			} catch (Exception $e) {
				$adapter->rollback();
				throw $e;
			}

			$this->_redirect('/admin/company/tag-cmp/company_id/'. $this->getparam("company_id"));
			return;

		}else if($this->_hasParam("back") && $this->getparam("back") != "") {
			$this->_forward("other-tag");
			return;
		}

		$this->view->assign("params", $params);
	}

	/**
	 * 物件用その他タグ設定用
	 */
    public function otherEstateTagAction()
    {
        $this->checkUserRules();
        
		if(!$this->_hasParam("company_id") || $this->getparam("company_id") == "" || !is_numeric($this->getparam("company_id"))) {
			throw new Exception("No Company ID. ");
			exit;
		}

		//オブジェクト取得
		$companyObj = new App_Model_DbTable_Company();
		$row = $companyObj->getDataForId($this->_getParam("company_id"));
		if($row == null) {
			throw new Exception("No Company Data. ");
			exit;
		}
		$this->view->company = $row;

		//パラメータ取得
		$params = $this->_request->getParams();

        $this->view->original_tag = $original_tag = App_Model_List_Original::getEffectMeasurementTitle();

		//パンクズ設定
    	$this->view->topicPath('契約管理', "index", $this->getRequest()->getControllerName());
		$pan_arr = array("id" => $this->_getParam("company_id"));
    	$this->view->topicPath("契約者詳細", "detail", $this->getRequest()->getControllerName(), $pan_arr);
    	$this->view->topicPath($original_tag);

		$tagObj = App_Model_DbTable_EstateTag::slave();

		//フォーム設定
		$this->view->form = $form = new Custom_Form();
		$form->assignSubForm(new Admin_Form_CompanyEstateTag(), 'other');

		if($this->_hasParam("submit") && $this->getparam("submit") != "") {

			//バリデーション
			if($form->isValid($params)) {
				//submit削除
				$this->_setParam("submit", "");
				$this->_setParam("back", "");
				$this->_forward("other-estate-tag-cnf");
				return;
			}

		}else if($this->_hasParam("back") || $this->getparam("back")) {
			unset($params['back']);
			$form->populate($params);

		} else {

			$row = $tagObj->getDataForCompanyId($this->getparam("company_id"));
			if($row != null || $row != false) {
				$row_data = $row->toArray();
				$form->populate($row_data);
			}
		}
		$form->populate($params);
		$this->view->assign("params", $params);
	}

	/**
	 * 物件用タグ設定用（確認）
	 */
    public function otherEstateTagCnfAction()
    {
        $this->checkUserRules();
        
		if(!$this->_hasParam("company_id") || $this->getparam("company_id") == "" || !is_numeric($this->getparam("company_id"))) {
			throw new Exception("No Company	 ID. ");
			exit;
		}

		//オブジェクト取得
		$companyObj = App_Model_DbTable_Company::slave();
		$row = $companyObj->getDataForId($this->_getParam("company_id"));
		if($row == null) {
			throw new Exception("No Company Data. ");
			exit;
		}

        $this->view->original_tag = $original_tag = App_Model_List_Original::getEffectMeasurementTitle();

		//パンクズ設定
    	$this->view->topicPath('契約管理', "index", $this->getRequest()->getControllerName());
		$pan_arr = array("id" => $this->_getParam("company_id"));
    	$this->view->topicPath("契約者詳細", "detail", $this->getRequest()->getControllerName(), $pan_arr);
    	$this->view->topicPath($original_tag);

		//パラメータ取得
		$params = $this->_request->getParams();


		if($this->_hasParam("submit") && $this->getparam("submit") != "") {
			//マスターで取る
			$tagObj = App_Model_DbTable_EstateTag::master();

			$data = array();
			$FormObj = new Admin_Form_CompanyEstateTag();
			foreach($FormObj as $key => $value){
				$data[$key]  = $params['other'][$key];
			}

			$table   = App_Model_DbTable_HpPage::master();
			$adapter = $table->getAdapter();
			try {
				$adapter->beginTransaction();
			
				//更新
			    if(isset($params['other']['id']) && $params['other']['id'] != "" && is_numeric($params['other']['id'])) {

				    $where = array("id = ?" => $params['other']['id']);
				    $tagObj->update($data, $where);

			    //新規
			    }else{
				    $id = $tagObj->insert($data);
			    }
				$adapter->commit();
			} catch (Exception $e) {
				$adapter->rollback();
				throw $e;
			}

			$this->_redirect('/admin/company/tag-cmp/company_id/'. $this->getparam("company_id"));
			return;

		}else if($this->_hasParam("back") && $this->getparam("back") != "") {
			$this->_forward("other-estate-tag");
			return;
		}

		$this->view->assign("params", $params);
	}

	/**
	 * 物件用その他タグ設定用
	 */
    public function otherEstateRequestTagAction()
    {
        $this->checkUserRules();
        
		if(!$this->_hasParam("company_id") || $this->getparam("company_id") == "" || !is_numeric($this->getparam("company_id"))) {
			throw new Exception("No Company ID. ");
			exit;
		}

		//オブジェクト取得
		$companyObj = new App_Model_DbTable_Company();
		$row = $companyObj->getDataForId($this->_getParam("company_id"));
		if($row == null) {
			throw new Exception("No Company Data. ");
			exit;
		}
		$this->view->company = $row;

		//パラメータ取得
		$params = $this->_request->getParams();

        $this->view->original_tag = $original_tag = App_Model_List_Original::getEffectMeasurementTitle();

		//パンクズ設定
    	$this->view->topicPath('契約管理', "index", $this->getRequest()->getControllerName());
		$pan_arr = array("id" => $this->_getParam("company_id"));
    	$this->view->topicPath("契約者詳細", "detail", $this->getRequest()->getControllerName(), $pan_arr);
    	$this->view->topicPath($original_tag);

		$tagObj = App_Model_DbTable_EstateRequestTag::slave();

		//フォーム設定
		$this->view->form = $form = new Custom_Form();
		$form->assignSubForm(new Admin_Form_CompanyEstateRequestTag(), 'other');

		if($this->_hasParam("submit") && $this->getparam("submit") != "") {

			//バリデーション
			if($form->isValid($params)) {
				//submit削除
				$this->_setParam("submit", "");
				$this->_setParam("back", "");
				$this->_forward("other-estate-request-tag-cnf");
				return;
			}

		}else if($this->_hasParam("back") || $this->getparam("back")) {
			unset($params['back']);
			$form->populate($params);

		} else {

			$row = $tagObj->getDataForCompanyId($this->getparam("company_id"));
			if($row != null || $row != false) {
				$row_data = $row->toArray();
				$form->populate($row_data);
			}
		}
		$form->populate($params);
		$this->view->assign("params", $params);
	}

	/**
	 * 物件用タグ設定用（確認）
	 */
    public function otherEstateRequestTagCnfAction()
    {
        $this->checkUserRules();
        
		if(!$this->_hasParam("company_id") || $this->getparam("company_id") == "" || !is_numeric($this->getparam("company_id"))) {
			throw new Exception("No Company	 ID. ");
			exit;
		}

		//オブジェクト取得
		$companyObj = App_Model_DbTable_Company::slave();
		$row = $companyObj->getDataForId($this->_getParam("company_id"));
		if($row == null) {
			throw new Exception("No Company Data. ");
			exit;
		}

        $this->view->original_tag = $original_tag = App_Model_List_Original::getEffectMeasurementTitle();

		//パンクズ設定
    	$this->view->topicPath('契約管理', "index", $this->getRequest()->getControllerName());
		$pan_arr = array("id" => $this->_getParam("company_id"));
    	$this->view->topicPath("契約者詳細", "detail", $this->getRequest()->getControllerName(), $pan_arr);
    	$this->view->topicPath($original_tag);

		//パラメータ取得
		$params = $this->_request->getParams();

		if($this->_hasParam("submit") && $this->getparam("submit") != "") {
			//マスターで取る
			$tagObj = App_Model_DbTable_EstateRequestTag::master();
			$data = array();
			$FormObj = new Admin_Form_CompanyEstateRequestTag();
			foreach($FormObj as $key => $value){
				$data[$key]  = $params['other'][$key];
			}

			$table   = App_Model_DbTable_HpPage::master();
			$adapter = $table->getAdapter();
			try {
				$adapter->beginTransaction();
			
				//更新
			    if(isset($params['other']['id']) && $params['other']['id'] != "" && is_numeric($params['other']['id'])) {

				    $where = array("id = ?" => $params['other']['id']);
				    $tagObj->update($data, $where);

			    //新規
			    }else{
				    $id = $tagObj->insert($data);
			    }
				$adapter->commit();
			} catch (Exception $e) {
				$adapter->rollback();
				throw $e;
			}
			$this->_redirect('/admin/company/tag-cmp/company_id/'. $this->getparam("company_id"));
			return;

		}else if($this->_hasParam("back") && $this->getparam("back") != "") {
			$this->_forward("other-estate-request-tag");
			return;
		}

		$this->view->assign("params", $params);
	}

	/**
	 * グループ設定用
	 */
    public function groupAction()
    {
        $this->checkUserRules();
        
		if(!$this->_hasParam("company_id") || $this->getparam("company_id") == "" || !is_numeric($this->getparam("company_id"))) {
			throw new Exception("No Company ID. ");
			exit;
		}

		//パラメータ取得
		$params = $this->_request->getParams();
		$this->view->assign("params", $params);

		//パンクズ設定
    	$this->view->topicPath('契約管理', "index", $this->getRequest()->getControllerName());
		$pan_arr = array("id" => $this->_getParam("company_id"));
    	$this->view->topicPath("契約者詳細", "detail", $this->getRequest()->getControllerName(), $pan_arr);
    	$this->view->topicPath("グループ会社設定");

		//オブジェクト取得
		$companyObj = new App_Model_DbTable_Company();
		$row = $companyObj->getDataForId($this->_getParam("company_id"));
		if($row == null) {
			throw new Exception("No Company Data. ");
			exit;
		}

		$this->view->company = $row;

    
		$table   = App_Model_DbTable_HpPage::master();
		$adapter = $table->getAdapter();
		try {
			$adapter->beginTransaction();
		
		    $acObj = App_Model_DbTable_AssociatedCompany::master();
		    if($this->_hasParam("add_company_id") && $this->getparam("add_company_id") != "" || is_numeric($this->getparam("add_company_id"))) {

			    $data = array();
			    $data['parent_company_id']     = $this->getparam("company_id");
			    $data['subsidiary_company_id'] = $this->getparam("add_company_id");
			    $acObj->insert($data);
                $adapter->commit();
			    $this->_redirect("/admin/company/group/?company_id=".$this->getparam("company_id"));
		    }

		    $rows = $acObj->getDataForCompanyId($this->_getParam("company_id"));
		    $this->view->rows = $rows;

		} catch (Exception $e) {
			$adapter->rollback();
			throw $e;
		}
	}

	/**
	 * グループ削除設定
	 */
    public function groupDelAction()
    {
        $this->checkUserRules();
        
		if(!$this->_hasParam("del_company_id") || $this->getparam("del_company_id") == "" || !is_numeric($this->getparam("del_company_id"))) {
			throw new Exception("No Company ID. ");
			exit;

		}else if(!$this->_hasParam("del_id") || $this->getparam("del_id") == "" || !is_numeric($this->getparam("del_id"))) {
			throw new Exception("No Del ID. ");
			exit;

		}else if(!$this->_hasParam("del_pearent_company_id") || $this->getparam("del_pearent_company_id") == "" || !is_numeric($this->getparam("del_pearent_company_id"))) {
			throw new Exception("No Del ID. ");
			exit;
		}

		$table   = App_Model_DbTable_HpPage::master();
		$adapter = $table->getAdapter();
		try {
			$adapter->beginTransaction();
		
		    $acObj = App_Model_DbTable_AssociatedCompany::master();

		    $data = array();
		    $data['delete_flg'] = 1;
		    $where = array("id = ?" => $this->getparam("del_id"), "parent_company_id = ?" => $this->getparam("del_pearent_company_id"), "subsidiary_company_id = ?" => $this->getparam("del_company_id"));
		    $acObj->update($data, $where);
			
			$adapter->commit();
		} catch (Exception $e) {
			$adapter->rollback();
			throw $e;
		}
		
		$this->_redirect("/admin/company/group/?company_id=".$this->getparam("del_pearent_company_id"));
		exit;
	}

	/**
	 * 契約者情報の削除設定
	 */
    public function deleteAction()
    {

		if(!$this->_hasParam("company_id") || $this->getparam("company_id") == "" || !is_numeric($this->getparam("company_id"))) {
			throw new Exception("No Company ID. ");
			exit;
		}

		//オブジェクト取得
		$companyObj = App_Model_DbTable_Company::master();
		$row = $companyObj->getDataForId($this->_getParam("company_id"));
		if($row == null) {
			throw new Exception("No Company Data. ");
			exit;
		}

		$data = array();
		$data['delete_flg'] = 1;

		$adapter = $companyObj->getAdapter();
		$adapter->beginTransaction();

		//契約情報の削除
		try {
			$where = array("id = ?" => $this->getparam("company_id"));
			$companyObj->update($data, $where);
		}catch(Exception $e) {
			$adapter->rollback();
			throw $e;
		}

		//その他も消しに行く（どこまで行く？）

		try {
			//加盟店アカウントテーブルの削除
			$caObj = App_Model_DbTable_CompanyAccount::master();
			$where = array("company_id = ?" => $this->getparam("company_id"));
			$caObj->update($data, $where);
		}catch(Exception $e) {
			$adapter->rollback();
			throw $e;
		}

		try {
			//加盟店とHPの紐付けテーブルの削除
			$achObj = App_Model_DbTable_AssociatedCompanyHp::master();
			$achObj->update($data, $where);
		}catch(Exception $e) {
			$adapter->rollback();
			throw $e;
		}

		try {
			//関連会社テーブルの削除
			$acObj = App_Model_DbTable_AssociatedCompany::master();
			$where = array("parent_company_id = ?" => $this->getparam("company_id"));
			$acObj->update($data, $where);
		}catch(Exception $e) {
			$adapter->rollback();
			throw $e;
		}

		try {
			$where = array("subsidiary_company_id = ?" => $this->getparam("company_id"));
			$acObj->update($data, $where);
		}catch(Exception $e) {
			$adapter->rollback();
			throw $e;
		}

		$adapter->commit();

		$this->_redirect("/admin/company/");
		exit;
	}

	/**
	 * ２次広告自動公開設定
	 */
    public function secondEstateAction()
    {
    	$this->checkUserRules();
    	// redirect company Lite
    	$companyObj = App_Model_DbTable_Company::master();
		$row = $companyObj->getDataForId($this->_getParam("company_id"));
		if($row->cms_plan == 10){
			$this->_redirect("/admin/company/");
		}

		// セッション
		$session = new Zend_Session_Namespace('admin-second-estate');
		$sParams = $session->params;

		//パンクズ設定
    	$this->view->topicPath('契約管理', "index", $this->getRequest()->getControllerName());
		$pan_arr = array("id" => $this->_getParam("company_id"));
    	$this->view->topicPath("契約者詳細", "detail", $this->getRequest()->getControllerName(), $pan_arr);
    	$this->view->topicPath("2次広告自動公開設定");
		

		//設定系の情報取得
		$company_config = new Zend_Config_Ini(APPLICATION_PATH ."/modules/admin/config/company.ini" , APPLICATION_ENV );
		

		//API系のURIなど
		$defailt_backbone = $company_config->backbone->api;
		$this->view->backbone = $defailt_backbone;

		//フォーム設定
		$this->view->form = $form = new Custom_Form();
		$form->assignSubForm(new Admin_Form_CompanySecondEstate(), 'secondEstate');
		$form->assignSubForm(new Admin_Form_CompanySecondEstateArea(), 'secondEstateArea');
		$form->assignSubForm(new Admin_Form_SecondEstateOther(), 'other');


		$companyObj = App_Model_DbTable_Company::slave();
		$companyAccountObj = App_Model_DbTable_CompanyAccount::slave();
        $secondEstate = App_Model_DbTable_SecondEstate::slave();

		//パラメータ取得
		$params = $this->_request->getParams();
		$this->view->assign("params", $params);


		//登録ボタン押下時
		if($this->_hasParam("submit-confirm") && $this->_getParam("submit-confirm") != "") {

			//バリデーション
			if($form->isValid($params)) {

				$error_flg = false;

				//利用日チェック
				if($params['secondEstate']['applied_start_date'] != "" && $params['secondEstate']['start_date'] != "")
				{
					$applied_start_date	= str_replace("-", "", $params['secondEstate']['applied_start_date'	] ) ;
					$start_date			= str_replace("-", "", $params['secondEstate']['start_date'			] ) ;
					if( $applied_start_date > $start_date ) {
						$form->secondEstate->getElement('applied_start_date')->addErrors( array("利用開始申請日は、利用開始日より過去日を設定してください。") );
						$error_flg = true;
					}
				}
				
				if( $params['secondEstate']['start_date'] != "" ) {
					$start_date = str_replace("-", "", $params['secondEstate']['start_date']);
					//2次広告自動公開設定
                    $row = $secondEstate->getDataForCompanyId($this->getparam("company_id"));
                    if($row != null) {
					    $db_start_date = substr($row['start_date'], 0, 10);
                    } else {
                    	$db_start_date = "0000-00-00";
                    }
                    // 新規登録の場合のみバリデーションチェックを行う。
                    if($db_start_date == "0000-00-00") {
                    	$dt = new DateTime();
                    	$dt->setTimeZone(new DateTimeZone('Asia/Tokyo'));
                    	$today = $dt->format('Ymd');
                    	if ( strtotime( $start_date ) <= strtotime( $today ) ) {
                            $form->secondEstate->getElement('start_date')->addErrors(
                            		array("利用開始日が当日の場合は物件が流れ込まない為、設定できません。",
                            				"翌日以降の日付けで設定して下さい。") );
                            $error_flg = true;
                    	}
                    }
				}
					
				//利用日チェック
				if($params['secondEstate']['applied_end_date'] != "" && $params['secondEstate']['end_date'] != "") {
					$applied_end_date = str_replace("-", "", $params['secondEstate']['applied_end_date']);
					$end_date = str_replace("-", "", $params['secondEstate']['end_date']);
					if($applied_end_date > $end_date) {
						$form->secondEstate->getElement('applied_end_date')->addErrors( array("利用停止申請日は、利用停止日より過去日を設定してください。") );
						$error_flg = true;
					}
				}

				//利用開始日と利用停止日のチェック
				if($params['secondEstate']['start_date'] != "" && $params['secondEstate']['end_date'] != "") {
					$start = str_replace("-", "", $params['secondEstate']['start_date']);
					$end = str_replace("-", "", $params['secondEstate']['end_date']);
					if($start > $end) {
						$form->secondEstate->getElement('end_date')->addErrors( array("利用停止日は、利用開始日より未来日を設定してください。") );
						$error_flg = true;
					}
				}

				//解約担当者系の設定
				if($params['secondEstate']['cancel_staff_id'] != "" && ($params['secondEstate']['cancel_staff_name'] == "" || $params['secondEstate']['cancel_staff_department'] == "")) {
					$form->secondEstate->getElement('cancel_staff_name')->addErrors( array("解約担当者名が設定されていません。参照ボタンより取得してください。") );
					$error_flg = true;
				}else if($params['secondEstate']['cancel_staff_id'] == "" && ($params['secondEstate']['cancel_staff_name'] != "" || $params['secondEstate']['cancel_staff_department'] != "")) {
					$form->secondEstate->getElement('cancel_staff_id')->addErrors( array("解約担当者が設定されていません。") );
					$error_flg = true;
				}

				if(!$error_flg) {
					$session->params = $params;
					$this->_redirect('/admin/company/second-estate-confirm?company_id='.$params["company_id"]);
					exit;					
				}

				$this->view->assign("params", $params);	


			}			

		//戻るボタン押下時
		}else if( is_array($session->params) && array_key_exists('back', $session->params) ){
			unset($session->params['back']);
			$form->populate($session->params);

		//
		}else{

			//2次広告自動公開設定
	        $row = $secondEstate->getDataForCompanyId($this->getparam("company_id"));

			if($row != null) {

				//日付周りの調整
				$applied_start_date = substr($row['applied_start_date'], 0, 10);
				if($applied_start_date == "0000-00-00") $applied_start_date = "";
				$row['applied_start_date'] = $applied_start_date;

				$start_date = substr($row['start_date'], 0, 10);
				if($start_date == "0000-00-00") $start_date = "";
				$row['start_date'] = $start_date;

				$applied_end_date = substr($row['applied_end_date'], 0, 10);
				if($applied_end_date == "0000-00-00") $applied_end_date = "";
				$row['applied_end_date'] = $applied_end_date;

				$end_date = substr($row['end_date'],0,  10);
				if($end_date == "0000-00-00") $end_date = "";
				$row['end_date'] = $end_date;
			}


	        if (!is_null($row)){
	    	    $area_search_filter = json_decode( $row->area_search_filter );
				$selectedPrefs = $area_search_filter->area_1;
				$secondEstateArea = $form->getSubForm('secondEstateArea');

				foreach ($secondEstateArea as $name => $element){
					$element->setValue($selectedPrefs);
				}

				$form->populate($row->toArray());

	        }
	    }




	}

	/**
	 * ２次広告自動公開設定
	 */
    public function secondEstateConfirmAction()
    {
        $this->checkUserRules();
        
		// セッション
		$session = new Zend_Session_Namespace('admin-second-estate');
		$sParams = $session->params;

		//パンクズ設定
    	$this->view->topicPath('契約管理', "index", $this->getRequest()->getControllerName());
		$pan_arr = array("id" => $this->_getParam("company_id"));
    	$this->view->topicPath("契約者詳細", "detail", $this->getRequest()->getControllerName(), $pan_arr);
    	$this->view->topicPath("2次広告自動公開設定");

		//モデル
		$companyObj = App_Model_DbTable_Company::slave();
		$companyAccountObj = App_Model_DbTable_CompanyAccount::slave();
        $secondEstate = App_Model_DbTable_SecondEstate::slave();

		//フォーム設定
		$this->view->form = $form = new Custom_Form();
		$form->assignSubForm(new Admin_Form_CompanySecondEstate(), 'secondEstate');
		$form->assignSubForm(new Admin_Form_CompanySecondEstateArea(), 'secondEstateArea');
		$form->assignSubForm(new Admin_Form_SecondEstateOther(), 'other');
		$form->populate($sParams);

		//パラメータ取得
		$params = $this->_request->getParams();
		$this->view->assign("params", $params);

		//登録ボタン押下時
		if($this->_hasParam("submit-complete") && $this->_getParam("submit-complete") != "") {
			$area_search_filter = array();
			$pref = array();

			// 都道府県番号
			foreach ($sParams['secondEstateArea'] as $area){
				foreach ($area as $value){
					$pref[] = $value;
				}
			}
			$area_search_filter['area_1'] = $pref;

			$area_search_filter = json_encode( $area_search_filter );
			$seParams = $sParams['secondEstate'];

			$data = array();
			$data['company_id'] 				= $sParams['company_id'];
			$data['applied_start_date'] 		= $seParams['applied_start_date'];
			$data['start_date'] 				= $seParams['start_date'];
			$data['contract_staff_id'] 			= $seParams['contract_staff_id'];
			$data['contract_staff_name'] 		= $seParams['contract_staff_name'];
			$data['contract_staff_department'] 	= $seParams['contract_staff_department'];
			$data['applied_end_date'] 			= empty($seParams['applied_end_date']) ? new Zend_Db_Expr("NULL") : $seParams['applied_end_date'];
			$data['end_date'] 					= empty($seParams['end_date']) ? new Zend_Db_Expr("NULL") : $seParams['end_date'];
			$data['cancel_staff_id'] 			= empty($seParams['cancel_staff_id']) ? new Zend_Db_Expr("NULL") : $seParams['cancel_staff_id'];
			$data['cancel_staff_name'] 			= empty($seParams['cancel_staff_name']) ? new Zend_Db_Expr("NULL") : $seParams['cancel_staff_name'];
            $data['cancel_staff_department'] 	= empty($seParams['cancel_staff_department']) ? new Zend_Db_Expr("NULL") : $seParams['cancel_staff_department'];
			$data['area_search_filter'] 		= $area_search_filter;
            $data['remarks'] 	            	= empty($sParams['other']['remarks']) ? "" : $sParams['other']['remarks'];

			$table   = App_Model_DbTable_HpPage::master();
			$adapter = $table->getAdapter();
			try {
				$adapter->beginTransaction();
			
							
			    if (is_null($seParams['id']) || empty($seParams['id'])){
				    $secondEstate->insert($data);
			    }else{
				    $where = array("id = ?" => $seParams['id']);	
				    $secondEstate->update($data, $where);
			    }
				
				$adapter->commit();
			} catch (Exception $e) {
				$adapter->rollback();
				throw $e;
			}

            $row = $companyObj->getDataForId($this->_getParam("company_id"));
            $this->_redirect('/admin/company/second-estate-complete?company_id='.$params['company_id']);
			exit;

		//戻るボタン押下時
		}else if($this->_hasParam("back") && $this->_getParam("back") != "") {
			$session->params['back'] = true;
			$this->_redirect("/admin/company/second-estate?company_id=".$params['company_id']);
		}

    }

	/**
	 * ２次広告自動公開設定
	 */
    public function secondEstateCompleteAction()
    {
		//パンクズ設定
    	$this->view->topicPath('契約管理', "index", $this->getRequest()->getControllerName());
		$pan_arr = array("id" => $this->_getParam("company_id"));
    	$this->view->topicPath("契約者詳細", "detail", $this->getRequest()->getControllerName(), $pan_arr);
    	$this->view->topicPath("2次広告自動公開設定");

		//設定系の情報取得
		$company_config = new Zend_Config_Ini(APPLICATION_PATH ."/modules/admin/config/company.ini" , APPLICATION_ENV );

		//API系のURIなど
		$defailt_backbone = $company_config->backbone->api;
		$this->view->backbone = $defailt_backbone;

		//フォーム設定
		$this->view->form = $form = new Custom_Form();
		$form->assignSubForm(new Admin_Form_CompanySecondEstate(), 'secondEstate');
		$form->assignSubForm(new Admin_Form_CompanySecondEstateArea(), 'secondEstateArea');
		$form->assignSubForm(new Admin_Form_SecondEstateOther(), 'other');

		//パラメータ取得
		$params = $this->_request->getParams();
		$this->view->assign("params", $params);
		
		//オブジェクト取得
		$companyObj = App_Model_DbTable_Company::slave();
		//契約者情報の取得
		$row = $companyObj->getDataForId($this->_getParam("company_id"));
		if($row == null) {
			throw new Exception("No Company Data. ");
			exit;
		}
		
		$this->view->contract_type		= $row[ "contract_type"			] ;
		$this->view->reserve_cms_plan	= $row[ "reserve_cms_plan"		] ;
		$this->view->cms_plan			= $row[ "cms_plan"				] ;
        $company_id = $this->_getParam("company_id");
        $isAdmin = Custom_User_Admin::getInstance()->checkIsSuperAdmin(Custom_User_Admin::getInstance()->getProfile());
        $isAgency = Custom_User_Admin::getInstance()->isAgency();

        if ($row->checkTopOriginal() && !$this->checkRedirectTopOriginal($row, $company_id, $isAdmin, $isAgency)) {
            $this->view->original_plan = true;
        }
        $this->view->original_setting_title = App_Model_List_Original::getOriginalSettingTitle();
        $this->view->original_edit_title = App_Model_List_Original::getOriginalEditTitle();
        $this->view->original_tag = App_Model_List_Original::getEffectMeasurementTitle();
	}


	/**
	 * 物件グループ
	 */
    public function estateGroupAction()
    {
    	$this->checkUserRules();
    	// redirect company Lite
    	$companyObj = App_Model_DbTable_Company::master();
		$row = $companyObj->getDataForId($this->_getParam("company_id"));
		if($row->cms_plan == 10){
			$this->_redirect("/admin/company/");
		}

		if(!$this->_hasParam("company_id") || $this->getparam("company_id") == "" || !is_numeric($this->getparam("company_id"))) {
			throw new Exception("No Company ID. ");
			exit;
		}

		//パラメータ取得
		$params = $this->_request->getParams();
		$this->view->assign("params", $params);

		//パンクズ設定
    	$this->view->topicPath('契約管理', "index", $this->getRequest()->getControllerName());
		$pan_arr = array("id" => $this->_getParam("company_id"));
    	$this->view->topicPath("契約者詳細", "detail", $this->getRequest()->getControllerName(), $pan_arr);
    	$this->view->topicPath("物件グループ設定");

		//オブジェクト取得
		$parentCompanyObj = new App_Model_DbTable_Company();
		$parentCompanyRow = $parentCompanyObj->getDataForId($this->_getParam("company_id"));
		if($parentCompanyRow == null) {
			throw new Exception("No Company Data. ");
			exit;
		}

		$this->view->company = $parentCompanyRow;

		$table   = App_Model_DbTable_HpPage::master();
		$adapter = $table->getAdapter();
		try {
			$adapter->beginTransaction();
		
		    $estateAccosiateObj = App_Model_DbTable_EstateAssociatedCompany::master();

		    // 追加の場合
		    if($this->_hasParam("add_member_no") && $this->getparam("add_member_no") != "" || is_numeric($this->getparam("add_member_no"))) {

			    $data = array();
			    $data['parent_company_id']    = $parentCompanyRow->id;
			    $data['subsidiary_member_no'] = $this->getparam("add_member_no");
			    $estateAccosiateObj->insert($data);
			$adapter->commit();

			    $this->_redirect("/admin/company/estate-group/?company_id=".$parentCompanyRow->id);
		    }

		    // 物件グループの一覧を取得する
		    $estateGroup = new Custom_Estate_Group();
		    $companies = $estateGroup->getSubCompanies($parentCompanyRow->id);
		    $this->view->companies = $companies;
			
			$adapter->commit();
		} catch (Exception $e) {
			$adapter->rollback();
			throw $e;
		}
	}	


	/**
	 * 物件グループ削除設定
	 */
    public function estateGroupDelAction()
    {

		if(!$this->_hasParam("del_member_no") || $this->getparam("del_member_no") == "" || !is_numeric($this->getparam("del_member_no"))) {
			throw new Exception("No member ID. ");
			exit;

		}else if(!$this->_hasParam("del_associate_id") || $this->getparam("del_associate_id") == "" || !is_numeric($this->getparam("del_associate_id"))) {
			throw new Exception("No Del ID. ");
			exit;

		}else if(!$this->_hasParam("del_parent_company_id") || $this->getparam("del_parent_company_id") == "" || !is_numeric($this->getparam("del_parent_company_id"))) {
			throw new Exception("No Parent Compnay id. ");
			exit;
		}
		$associate_id         = $this->getparam("del_associate_id");  
		$parent_company_id    = $this->getparam("del_parent_company_id");
		$subsidiary_member_no = $this->getparam("del_member_no");

		
		$table   = App_Model_DbTable_HpPage::master();
		$adapter = $table->getAdapter();
		try {
			$adapter->beginTransaction();
		
		    $estateAccosiateObj = App_Model_DbTable_EstateAssociatedCompany::master();

			$data = array();
		    $data['delete_flg'] = 1;
		    $where = array("id = ?" => $associate_id, "parent_company_id = ?" => $parent_company_id , "subsidiary_member_no = ?" => $subsidiary_member_no);
		    $estateAccosiateObj->update($data, $where);
			
			$adapter->commit();
		} catch (Exception $e) {
			$adapter->rollback();
			throw $e;
		}
		
		$this->_redirect("/admin/company/estate-group/?company_id=".$parent_company_id );
		exit;
	}

    /**
     * Top original setting
     */
    public function originalSettingAction()
    {
        $this->checkUserRules();
        
        $session = new Zend_Session_Namespace('admin-original-setting');
        $sParams = $session->params;

        $companyObj = App_Model_DbTable_Company::slave();
        $company_id = $this->_getParam("company_id");
        $controller = $this->getRequest()->getControllerName();
        $row = $companyObj->getDataForId($company_id);

        if(!App_Model_List_Original::checkPlanCanUseTopOriginal($row->cms_plan)){
            $this->_redirect('/admin/company/detail?id='.$company_id);
            exit;
        }

        $this->view->topicPath('契約管理', "index", $controller);
        $this->view->topicPath("契約者詳細", "detail", $controller, array("id" => $company_id));
        $this->view->topicPath(App_Model_List_Original::getOriginalSettingTitle());

        $company_config = new Zend_Config_Ini(APPLICATION_PATH ."/modules/admin/config/company.ini" , APPLICATION_ENV );

        $defailt_backbone = $company_config->backbone->api;
        $this->view->backbone = $defailt_backbone;
        $this->view->original_title = App_Model_List_Original::getOriginalSettingTitle();
        $this->view->contract_title = App_Model_List_Original::CONTRACT_TITLE;

        $this->view->form = $form = new Custom_Form();
        $form->assignSubForm(new Admin_Form_CompanySecondEstate(), 'originalSetting');
        $form->assignSubForm(new Admin_Form_CompanyRegistOther(), 'other');

        $originalSetting = App_Model_DbTable_OriginalSetting::slave();

        $params = $this->_request->getParams();
        $this->view->assign("params", $params);

        if ($this->_hasParam("submit-confirm") && $this->_getParam("submit-confirm") != "") {
            if ($form->isValid($params)) {
                $error_flg = false;
                if ($params['originalSetting']['applied_start_date'] != "" && $params['originalSetting']['start_date'] != "") {
                    $applied_start_date = str_replace("-", "", $params['originalSetting']['applied_start_date']);
                    $start_date = str_replace("-", "", $params['originalSetting']['start_date']);
                    if ($applied_start_date > $start_date) {
                        $form->originalSetting->getElement('applied_start_date')->addErrors( array("利用開始申請日は、利用開始日より過去日を設定してください。"));
                        $error_flg = true;
                    }
                }

                if ($params['originalSetting']['applied_end_date'] != "" && $params['originalSetting']['end_date'] != "") {
                    $applied_end_date = str_replace("-", "", $params['originalSetting']['applied_end_date']);
                    $end_date = str_replace("-", "", $params['originalSetting']['end_date']);
                    if ($applied_end_date > $end_date) {
                        $form->originalSetting->getElement('applied_end_date')->addErrors( array("利用停止申請日は、利用停止日より過去日を設定してください。"));
                        $error_flg = true;
                    }
                }

                if ($params['originalSetting']['start_date'] != "" && $params['originalSetting']['end_date'] != "") {
                    $start = str_replace("-", "", $params['originalSetting']['start_date']);
                    $end = str_replace("-", "", $params['originalSetting']['end_date']);
                    if ($start > $end) {
                        $form->originalSetting->getElement('end_date')->addErrors( array("利用停止日は、利用開始日より未来日を設定してください。"));
                        $error_flg = true;
                    }
                }

                if ($params['originalSetting']['cancel_staff_id'] != "" && ($params['originalSetting']['cancel_staff_name'] == "" || $params['originalSetting']['cancel_staff_department'] == "")) {
                    $form->originalSetting->getElement('cancel_staff_name')->addErrors( array("解約担当者名が設定されていません。参照ボタンより取得してください。"));
                    $error_flg = true;
                } else if($params['originalSetting']['cancel_staff_id'] == "" && ($params['originalSetting']['cancel_staff_name'] != "" || $params['originalSetting']['cancel_staff_department'] != "")) {
                    $form->originalSetting->getElement('cancel_staff_id')->addErrors( array("解約担当者が設定されていません。") );
                    $error_flg = true;
                }

                if (!$error_flg) {
                    $session->params = $params;
                    $this->_redirect('/admin/company/original-setting-confirm?company_id='.$params["company_id"]);
                    exit;
                }
                $this->view->assign("params", $params);
            }
        }else if( is_array($session->params) && array_key_exists('back', $session->params) ){
            unset($session->params['back']);
            $form->populate($session->params);
        }else{
            $row = $originalSetting->getDataForCompanyId($this->getparam("company_id"));
            if ($row != null) {
                $applied_start_date = substr($row['applied_start_date'], 0, 10);
                if($applied_start_date == "0000-00-00") $applied_start_date = "";
                $row['applied_start_date'] = $applied_start_date;

                $start_date = substr($row['start_date'], 0, 10);
                if($start_date == "0000-00-00") $start_date = "";
                $row['start_date'] = $start_date;

                $applied_end_date = substr($row['applied_end_date'], 0, 10);
                if($applied_end_date == "0000-00-00") $applied_end_date = "";
                $row['applied_end_date'] = $applied_end_date;

                $end_date = substr($row['end_date'],0,  10);
                if($end_date == "0000-00-00") $end_date = "";
                $row['end_date'] = $end_date;
            }

            if (!is_null($row)){
                $form->populate($row->toArray());
            }
        }
    }

    /**
     * Top original setting confirm
     */
    public function originalSettingConfirmAction()
    {
        $this->checkUserRules();
        
        $session = new Zend_Session_Namespace('admin-original-setting');
        $sParams = $session->params;

        $companyObj = App_Model_DbTable_Company::slave();
        $company_id = $this->_getParam("company_id");
        if(!is_numeric($company_id)){
            $this->_redirect('/admin/company');
            exit;
        }
        $controller = $this->getRequest()->getControllerName();
        /** @var App_Model_DbTable_Company_Row $row */
        $row = $companyObj->getDataForId($company_id);

        //
        if(!$row || !App_Model_List_Original::checkPlanCanUseTopOriginal($row->cms_plan)){
            $this->_redirect('/admin/company/detail/?id='.$company_id);
            exit;
        }

        $this->view->topicPath('契約管理', "index", $controller);
        $this->view->topicPath("契約者詳細", "detail", $controller, array("id" => $company_id));
        $this->view->topicPath(App_Model_List_Original::getOriginalSettingTitle());

        $this->view->form = $form = new Custom_Form();
        $form->assignSubForm(new Admin_Form_CompanySecondEstate(), 'originalSetting');
        $form->assignSubForm(new Admin_Form_CompanyRegistOther(), 'other');
        $form->populate($sParams);

        $originalSetting = App_Model_DbTable_OriginalSetting::master();

        $params = $this->_request->getParams();
        $this->view->assign("params", $params);
        $this->view->original_title = App_Model_List_Original::getOriginalSettingTitle();
        $this->view->original_sub_title = App_Model_List_Original::getOriginalSettingSubTitle();

        if ($this->_hasParam("submit-complete") && $this->_getParam("submit-complete") != "") {
            $seParams = $sParams['originalSetting'];
            $data = array();
            $data['company_id'] = $sParams['company_id'];
            $data['applied_start_date'] = $seParams['applied_start_date'];
            $data['start_date'] = $seParams['start_date'];
            $data['contract_staff_id'] = $seParams['contract_staff_id'];
            $data['contract_staff_name'] = $seParams['contract_staff_name'];
            $data['contract_staff_department'] = $seParams['contract_staff_department'];
            $data['applied_end_date'] = empty($seParams['applied_end_date']) ? new Zend_Db_Expr("NULL") : $seParams['applied_end_date'];
            $data['end_date'] = empty($seParams['end_date']) ? new Zend_Db_Expr("NULL") : $seParams['end_date'];
            $data['cancel_staff_id'] = empty($seParams['cancel_staff_id']) ? new Zend_Db_Expr("NULL") : $seParams['cancel_staff_id'];
            $data['cancel_staff_name'] = empty($seParams['cancel_staff_name']) ? new Zend_Db_Expr("NULL") : $seParams['cancel_staff_name'];
            $data['cancel_staff_department'] = empty($seParams['cancel_staff_department']) ? new Zend_Db_Expr("NULL") : $seParams['cancel_staff_department'];
            $data['remarks'] = empty($sParams['other']['remarks']) ? "" : $sParams['other']['remarks'];

            $adapter = $originalSetting->getAdapter();
            try {
                $adapter->beginTransaction();

                $topBefore = $row->checkTopOriginal();
                
                if (is_null($seParams['id']) || empty($seParams['id'])){
                    $originalSetting->insert($data);
                } else{
                    $where = array("id = ?" => $seParams['id']);
                    $originalSetting->update($data, $where);
                }

                $originalSettingData = $originalSetting->getDataForCompanyId($row->id);

                $checkStartDate = App_Model_List_Original::checkDate($originalSettingData->start_date);
                $checkEndDate = App_Model_List_Original::checkDate($originalSettingData->end_date);

                switch ($checkStartDate){
                    // start date < today, if is top => exe now
                    case App_Model_List_Original::PAST_DATE:
                        $topTo = true;
                        // expired?? Remove top.
                        if($checkEndDate == App_Model_List_Original::PAST_DATE) {
                            $topTo = false;
                        }
                        break;
                    default:
                        $topTo = false;
                }
                App_Model_List_Original::callTopOriginalEvent($row, $topTo, $topBefore);
                $adapter->commit();
            } catch (Exception $e) {
                $adapter->rollback();
                throw $e;
            }

            $this->_redirect('/admin/company/detail?id='.$params['company_id']);
            exit;

        } else if($this->_hasParam("back") && $this->_getParam("back") != "") {
            $session->params['back'] = true;
            $this->_redirect("/admin/company/original-setting?company_id=".$params['company_id']);
        }
    }

    public function originalEditAction()
    {
        $company_id = $this->_getParam("company_id");
        $controller = $this->getRequest()->getControllerName();
        $companyObj = App_Model_DbTable_Company::slave();
        $row = $companyObj->getDataForId($company_id);
        $isAdmin = Custom_User_Admin::getInstance()->checkIsSuperAdmin(Custom_User_Admin::getInstance()->getProfile());
        $isAgency = Custom_User_Admin::getInstance()->isAgency();

        if ($this->checkRedirectTopOriginal($row, $company_id, $isAdmin, $isAgency)) {
            $this->_redirect('/admin/company/detail?id='.$company_id);
            exit;
        }
        $this->view->topicPath('契約管理', "index", $controller);
        $this->view->topicPath("契約者詳細", "detail", $controller, array("id" => $company_id));
        $this->view->topicPath(App_Model_List_Original::getOriginalEditTitle());
        $this->view->original_edit_title = App_Model_List_Original::getOriginalEditTitle();
        $this->view->original_edit_sub_title = App_Model_List_Original::getOriginalEditSubTitle();
        $params = $this->_request->getParams();
        $this->view->assign("params", $params);

        $this->view->original_link = App_Model_List_Original::getOriginalName($company_id);
    }

    /**
     * 06_契約管理:グロナビ設定／オリジナルタグ編集・一覧
     * Setting global navigation/Edit original tag-list
     * @throws Exception
     */
    public function navigationTagListAction(){
        $companyId = $this->getParam("company_id");
        $row = $this->_checkCompanyTOP($companyId);

        $originalClass = $this->originalClass;
        $screenId = $originalClass::ORIGINAL_EDIT_NAVIGATION;
        $detailScreenId = $originalClass::ORIGINAL_EDIT_CMS;
        $specialScreenId = $originalClass::ORIGINAL_EDIT_SPECIAL;
        //パンクズ設定 - Breadcrumb
        $this->breadcrumbTOPEdit($originalClass::getScreenTitle($screenId),$companyId);

        $isAdmin = Custom_User_Admin::getInstance()->checkIsSuperAdmin(Custom_User_Admin::getInstance()->getProfile());
        $isAgency = Custom_User_Admin::getInstance()->isAgency();

        if ($this->checkRedirectTopOriginal($row, $companyId, $isAdmin, $isAgency)) {
            $this->_redirect('/admin/company/detail?id='.$companyId);
            exit;
        }

        // get params;
        $params = $this->_request->getParams();

        $params['backTOP'] = $originalClass::getScreenUrl($detailScreenId,$companyId);
        $params['redirectEditHousingBlock'] = $originalClass::getScreenUrl($specialScreenId,$companyId);
        $params['currentUrl'] = $originalClass::getScreenUrl($screenId,$companyId);

        // get current HP
        /** @var App_Model_DbTable_Hp_Row $hp */
        $hp = $row->getCurrentCreatorHp();

        if(!$hp){
            $this->view->hp = null;
            $this->view->assign('params', $params);
            return;
        }

        $params['max_global_navigation'] = App_Model_List_Original::MAX_GLOBAL_NAVIGATION;

        //create forms
        $formNavigation = new Admin_Form_TopGlobalNavigation();
        $this->view->form = $form = new Custom_Form();
        $form->assignSubForm( $formNavigation, 'navigation');
        $formNavigation->populate([
            'global_navigation' => $hp->global_navigation
        ]);
        $form->populate($params);

        // submit navigation
        if($this->getRequest()->isXmlHttpRequest() && $this->getRequest()->isPost()) {
            if (isset($params['navigation'])) {
                if ($formNavigation->isValid($params['navigation'])) {
                    $nav = $params['navigation']['global_navigation'];
                    $hp->global_navigation = $nav;
                    $hp->save();
                    $this->_responseJSON(200, $this->text->get('global_navigation.success'));
                    exit;
                }
            }
            $this->_responseJSON(400, $this->text->get('error'));
            exit;
        }

        // get Global Navigation
        $globalNav = $hp->getGlobalNavigation();
        $gNavArr = array();
        if($globalNav){
            $estateSetting = $hp->getEstateSetting();
            $gNavArr = $globalNav->toArray();
            $realTitle = false;
            if(isset($params['display']) && $params['display'] == '1'){
                // read real name menu agency cms
                $realTitle = true;
            }
            foreach($gNavArr as $k => &$v){
                $v['title'] = App_Model_List_Original::getPageTitle($v,$estateSetting,$realTitle);
            }
        }
        $this->view->gNav = $gNavArr ;

        $tagsObject = new App_Model_List_TagOriginal();

        $this->view->tags = array(
            'tag_site'      => $tagsObject->getValueTagsWithChunk(3,$tagsObject::CATEGORY_TAG_SITE),
            'tag_property'  => $tagsObject->getValueTagsWithChunk(3,$tagsObject::CATEGORY_TAG_PROPERTY),
            'tag_news'      => $tagsObject->getValueTagsWithChunk(3,$tagsObject::CATEGORY_TAG_NEWS) ,
            'tag_component' => $tagsObject->getValueTagsWithChunk(3,$tagsObject::CATEGORY_TAG_COMPONENT),
            'tag_element'   => $tagsObject->getValueTags($tagsObject::CATEGORY_TAG_ELEMENT)
        );

        // fixed tags
        $this->view->tags_nav = (object)[
            'glonavi_url' => $tagsObject::GLONAVI_URL,
            'glonavi_label' => $tagsObject::GLONAVI_LABEL,
        ];

        // global navigation sp tag
        $this->view->tags_spglonavi = $tagsObject->getSpGloNavi();

        $di = $this->getCurrentDirectory();
        $di->load(App_Model_List_Original::getOriginalImportPath($companyId));

        $this->view->di = $di;
        $this->view->hp = $hp;
        $this->view->assign('params', $params);
    }

    /**
     * 07_Housing Block | Special Estate | Koma
     * 07_契約管理:物件特集コマ編集
     * @throws Exception
     */
    public function topHousingBlockAction(){
        $companyId = $this->getParam("company_id");
        $row = $this->_checkCompanyTOP($companyId);
        $originalClass = $this->originalClass;
        //パンクズ設定 - Breadcrumb
        $this->breadcrumbTOPEdit(
            $originalClass::getScreenTitle($originalClass::ORIGINAL_EDIT_SPECIAL),
            $companyId
        );

        $isAdmin = Custom_User_Admin::getInstance()->checkIsSuperAdmin(Custom_User_Admin::getInstance()->getProfile());
        $isAgency = Custom_User_Admin::getInstance()->isAgency();

        if ($this->checkRedirectTopOriginal($row, $companyId, $isAdmin, $isAgency)) {
            $this->_redirect('/admin/company/detail?id='.$companyId);
            exit;
        }

        $params = $this->_request->getParams();

        $params['companyId'] = $companyId;
        $params['currentUrl'] = $originalClass::getScreenUrl($originalClass::ORIGINAL_EDIT_SPECIAL,$companyId);
        $params['backTOP'] = $originalClass::getScreenUrl($originalClass::ORIGINAL_EDIT_CMS,$companyId);

        $this->view->links = array(
            'html' => App_Model_List_Original::getOriginalImportUrl($companyId,App_Model_List_Original::ORIGINAL_IMPORT_TOPKOMA),
            'image' => App_Model_List_Original::getOriginalImportUrl($companyId,App_Model_List_Original::ORIGINAL_IMPORT_TOPIMAGE),
            'css' => App_Model_List_Original::getOriginalImportUrl($companyId,App_Model_List_Original::ORIGINAL_IMPORT_TOPCSS),
            'js' => App_Model_List_Original::getOriginalImportUrl($companyId,App_Model_List_Original::ORIGINAL_IMPORT_TOPJS),
        );

        $this->view->form = new Admin_Form_TopHousingBlock();

        // get current HP
        /** @var App_Model_DbTable_Hp_Row $hp */
        $hp = $row->getCurrentCreatorHp();

        if(!$hp){
            $this->view->hp = null;
            $this->view->settings = null;
            $this->view->assign('params', $params);
            return;
        }

        $settings = $hp->getEstateSetting();
        if(!$settings){
            $this->view->hp = $hp;
            $this->view->settings = null;
            $this->view->assign('params', $params);
            return;
        }
        $komaKey = Custom_Hp_Page_Parts_EstateKoma::SPECIAL_ID_ATTR;
        // get HomePage
        $topPage = App_Model_DbTable_HpPage::slave()->getTopPageData($hp->id);
        // get all koma parts in homepage
        $komaParts = $topPage->fetchPartsWithOrder(App_Model_DbTable_HpMainParts::PARTS_ESTATE_KOMA,"ABS($komaKey) DESC");

        $komaClass = new Custom_Hp_Page_Parts_EstateKoma(
            array('hp' => $hp, 'page'=> $topPage)
        );

        $komaClass->disableDefault(array(
            'parts_type_code',
            'sort',
            'column_sort',
            'display_flg'
        ));


        $this->view->form = $this->_generateSpecialForm($komaParts,$settings,$komaClass) ;


        $this->view->hp = $hp;
        $this->view->settings = $settings;


        if($this->getRequest()->isPost()){
            if($this->getRequest()->isXmlHttpRequest()) {
                if (isset($params['parts'])) {

                    $table = Custom_Db_Table_Abstract::master();
                    $adapter = $table->getAdapter();

                    /**
                     * @var App_Model_DbTable_SpecialEstate_Row $value
                     */
                    try {
                        $adapter->beginTransaction();

                        foreach ($komaParts as $k => $value) {
                            if(!isset($params['parts'][$value->id])) continue;
                            $dataSetting = $params['parts'][$value->id];
                            $komaFormCheck = clone $komaClass;
                            if ($komaFormCheck->isValid($dataSetting)) {
                                $dataSetting['id'] = $value->id;
                                $komaFormCheck->setDefaults($dataSetting);
                                $komaFormCheck->save($hp,$topPage,$value->area_id);
                            } else {
                                $adapter->rollBack();
                                $this->_responseJSON(400, $this->text->get('special_estate.setting.error'), array(
                                    'errors' => $komaFormCheck->getMessages()
                                ));
                                exit;
                            }
                            unset($komaFormCheck);
                            unset($dataSetting);
                        }
                        $adapter->commit();
                        $this->_responseJSON(200, $this->text->get('special_estate.setting.success'));
                    } catch (\Exception $e) {
                        $adapter->rollBack();
                        $this->_responseJSON(400, $this->text->get('special_estate.setting.error'));
                    }
                    exit;
                }
                $this->_responseJSON(400, $this->text->get('special_estate.setting.error'));
                exit;
            }
        }
        
        $this->view->assign('params', $params);
    }

    /**
     * @throws Zend_Controller_Response_Exception
     */
    public function apiReadTopHousingBlockAction(){

        /** @var Custom_View_Helper_TopOriginalLang $lang */
        $lang =  $this->view->topOriginalLang();
        $params = $this->_request->getParams();
        $companyId = $params['company_id'];

        /** @var App_Model_DbTable_Company_Row $company */
        $company = App_Model_DbTable_Company::slave()->getDataForId($companyId);

        if(!$company){
            return $this->_responseJSON(400, $lang->get('error'));
        }

        $table = Custom_Db_Table_Abstract::master();
        $adapter = $table->getAdapter();

        try {
            $adapter->beginTransaction();

            App_Model_List_Original::readSpecial($company);

            $adapter->commit();

            $this->_responseJSON(200, $lang->get('special_estate.setting.read_specials.success'));

        } catch (\Exception $e) {
            $adapter->rollBack();

            $this->_responseJSON(400, $lang->get('error'));
        }

    }

    /**
     * @param Zend_Db_Table_Rowset_Abstract $komaParts
     * @param App_Model_DbTable_HpEstateSetting_Row $settings
     * @param Custom_Hp_Page_Parts_EstateKoma $komaClass
     * @return Admin_Form_TopHousingBlock
     */
    protected function _generateSpecialForm($komaParts, $settings, $komaClass){

        $komaId = $komaClass::SPECIAL_ID_ATTR ;

        $ids = array_map(function ($ar) use ($komaId) {
            return $ar[$komaId];
        }, $komaParts->toArray());

        $specials = $settings->getSpecialAllWithPubStatus();

        $form = new Admin_Form_TopHousingBlock();

        foreach($komaParts as $k => $part){
            /**
             * @var $part App_Model_DbTable_SpecialEstate_Rowset
             */
            $partClass = clone $komaClass;
            $form->assignSubForm($partClass,"parts[$part->id]");
            $partClass->populate($part->toArray());

            /**
             *@var $currentSpecial App_Model_DbTable_SpecialEstate_Row
             */
            $currentSpecial = null;

            foreach($specials as $special){
                if($part->$komaId == $special->origin_id){
                    $currentSpecial = $special;
                    break;
                }
            }

            if($currentSpecial){

                $currentSpecialData = $currentSpecial->toArray();

                $detailForm = new Admin_Form_TopHousingBlock();

                $partClass->assignSubForm( $detailForm,"parts[$part->id][detail]");

                $currentSpecialData['alias'] = 'special_'.$currentSpecial->origin_id;

                $currentSpecialData['publish_status'] = ($currentSpecial->is_public)
                    ? $this->text->get('special_estate.publish_status.public')
                    : $this->text->get('special_estate.publish_status.not_public') ;

                $types = $currentSpecial->toSettingObject()->getDisplayEstateType();
                $currentSpecialData['type'] = implode(' - ', array_map("trim",array_filter($types)));

                $detailForm->populate($currentSpecialData);
            }

        }

        return $form;
    }


    /**
     * 08_Notifications
     * 08_契約管理:お知らせ設定
     * @throws Exception
     */
    public function topNotificationAction(){
        $companyId = $this->getParam("company_id");
        $row = $this->_checkCompanyTOP($companyId);
        $originalClass = $this->originalClass;
        $screenId = $originalClass::ORIGINAL_EDIT_NOTIFICATION;
        $detailScreenId = $originalClass::ORIGINAL_EDIT_CMS;

        //パンクズ設定 - Breadcrumb
        $this->breadcrumbTOPEdit($originalClass::getScreenTitle($screenId),$companyId);

        $params = $this->_request->getParams();
        $params['backTOP'] = $originalClass::getScreenUrl($detailScreenId,$companyId);

        $isAdmin = Custom_User_Admin::getInstance()->checkIsSuperAdmin(Custom_User_Admin::getInstance()->getProfile());
        $isAgency = Custom_User_Admin::getInstance()->isAgency();

        if ($this->checkRedirectTopOriginal($row, $companyId, $isAdmin, $isAgency)) {
            $this->_redirect('/admin/company/detail?id='.$companyId);
            exit;
        }

        // get current HP
        $hp = $row->getCurrentCreatorHp();

        if(!$hp){
            $this->view->hp = null;
            $this->view->assign('params', $params);
            return;
        }
        
        $topPage = App_Model_DbTable_HpPage::slave()->getTopPageData($hp->id);

        $notificationSettingForm = new Admin_Form_TopNotificationSetting(array(
            'hp' => $hp,
            'page' => $topPage
        ));

        $mainPartObj = App_Model_DbTable_HpMainParts::slave();

        $settings = $mainPartObj->getAllNotificationSettings($topPage->id);

        $pages = $hp->findPagesByType(App_Model_DbTable_HpPage::TYPE_INFO_INDEX, false);

        $form = new Custom_Form();

        $notificationForm = new Admin_Form_TopNotificationForm(array(
            'settings' => $settings,
            'hp' => $hp
        ));

        foreach(array('create','edit','delete') as $value){
            $apiForm = clone $notificationForm;
            $form->assignSubForm($apiForm,$value);
        }

        $this->view->newsArr = $notificationForm->getParents();

        $form->assignSubForm( new Custom_Form(),'page_settings');

        $this->view->form = $form;

        if(count($pages) > 0){
            $pagesForm = new Custom_Form();
            $form->assignSubForm($pagesForm,'pages');
            /**
             * @var  $k
             * @var App_Model_DbTable_HpMainParts $setting
             */
            foreach($settings as $k=>$setting){

                $idField = App_Model_List_Original::$EXTEND_INFO_LIST['page_id'];

                $details = array();
                /**
                 * @var  $k
                 * @var App_Model_DbTable_HpPage_Row $page
                 */
                foreach($pages as $page){
                    if($page->link_id != $setting->$idField) continue;

                    $settingForm = clone $notificationSettingForm;
                    $pagesForm->assignSubForm($settingForm,"settings[$k]");
                    $settingForm->populate($setting->toArray());
                    $details = $page->fetchNewsCategories();
                    break;
                }

                if(!isset($settingForm) || !$settingForm) continue;

                if(count($details)<1){
                    continue;
                }

                foreach($details as $key => $detail ){
                    $detailForm = clone $notificationForm;
                    $settingForm->assignSubForm($detailForm,"details[$key]");
                    $detailForm->populate($detail->toArray());
                }
            }
        }

        if($this->getRequest()->isPost() && $this->getRequest()->isXmlHttpRequest()){
            if(isset($params['page_settings']) && isset($params['settings'])){

                $table   = Custom_Db_Table_Abstract::master();
                $adapter = $table->getAdapter();

                try {
                    $adapter->beginTransaction();

                    // save settings for info index
                    foreach($params['settings'] as $k => $paramPage){
                        $checkSettingForm = clone $notificationSettingForm;
                        if(!$checkSettingForm->isValid($paramPage)){
                            $this->_responseJSON(400,$this->text->get('notification_settings.settings.error'),[
                                'errors' => $checkSettingForm->getMessages()
                            ]);
                            $adapter->rollBack();
                            exit;
                        }
                        $checkSettingForm->saveSetting();
                    }

                    //save sort
                    /** @var Admin_Form_TopNotificationForm $cateObject */
                    if(isset($params['sort']) && is_array($params['sort'])){
                        $cateObject = $this->view->form->edit;
                        $cateObject->massSort($hp->id,$params['sort']);
                    }

                    $adapter->commit();
                    $this->_responseJSON(200,$this->text->get('notification_settings.settings.success'));
                }
                catch (\Exception $e){
                    $this->_responseJSON(400,$e->getMessage());
                    $adapter->rollBack();
                }
                exit;
            }


            if(isset($params['create'])){
                //check create post
                $createArr = $params['create'];

                $createFormCheck = new Admin_Form_TopNotificationForm(array(
                    'hp' => $hp,
                    'settings' => $settings,
                    'parentId' => $createArr['parent_page_id']
                ));

                if(isset($params['preview']) && ($params['preview'] == true || $params['preview'] == 'true')){

                    if($createFormCheck->isValid($createArr)) {
                        $this->_responseJSON(200,$this->text->get('notification_settings.create.success'));
                    }
                    else {
                        $messages = $createFormCheck->getMessages();
                        $this->_responseJSON(400,$this->text->get('notification_settings.create.error'), array(
                            'errors' => $messages
                        ));
                    }
                    exit;
                }

                $table   = Custom_Db_Table_Abstract::master();
                $adapter = $table->getAdapter();

                try {
                    $adapter->beginTransaction();

                    if($createFormCheck->isValid($createArr)) {

                        $row = $createFormCheck->saveData();

                        $adapter->commit();

                        $this->_responseJSON(200,$this->text->get('notification_settings.create.success'), array(
                            'data' => $row
                        ));
                    }
                    else {
                        $messages = $createFormCheck->getMessages();
                        $this->_responseJSON(400,$this->text->get('notification_settings.create.error'), array(
                            'errors' => $messages
                        ));
                        $adapter->rollback();
                    }
                } catch (Exception $e) {
                    $adapter->rollback();
                    $this->_responseJSON(400,$e->getMessage());
                }
                exit;
            }

            //check update post
            if(isset($params['edit'])){

                $updateArr = $params['edit'];

                $table   = Custom_Db_Table_Abstract::master();

                /** @var Admin_Form_TopNotificationForm $formCheckUpdate */
                $formCheckUpdate = new Admin_Form_TopNotificationForm(array(
                    'settings' => $settings,
                    'hp' => $hp,
                    'id' => $updateArr['id'],
                    'parentId' => $updateArr['parent_page_id']
                ));

                if($formCheckUpdate->isValid($updateArr)) {

                    $adapter = $table->getAdapter();
                    try {
                        $adapter->beginTransaction();

                        $row = $formCheckUpdate->saveData();

                        $adapter->commit();


                        $this->_responseJSON(200,$this->text->get('notification_settings.update.success'),[
                            'data' => $row
                        ]);


                    } catch (Exception $e) {
                        $adapter->rollback();
                        $this->_responseJSON(400,$this->text->get('notification_settings.update.error'));
                    }
                }
                else {
                    $messages = $formCheckUpdate->getMessages();
                    $this->_responseJSON(400,$this->text->get('notification_settings.update.error'), array(
                        'errors' => $messages
                    ));
                }
                exit;
            }

            // check delete post
            if(isset($params['delete'])){

                $deleteArr = $params['delete'];

                $table   = Custom_Db_Table_Abstract::master();

                $adapter = $table->getAdapter();
                try {
                    $adapter->beginTransaction();

                    $this->view->form->delete->deleteData($deleteArr['id']);

                    App_Model_DbTable_AssociatedHpPageAttribute::master()->delete(array(
                        'hp_id = ?' => $hp->id,
                        'hp_main_parts_id' => $deleteArr['id']
                    ));

                    $adapter->commit();

                    $this->_responseJSON(200,$this->text->get('notification_settings.delete.success'),array(
                        'data' => array( 'id' => $deleteArr['id'] )
                    ));


                } catch (Exception $e) {
                    $adapter->rollback();
                    $this->_responseJSON(400,$this->text->get('notification_settings.delete.error'));
                }

                exit;
            }

            $this->_responseJSON(400,'');
            exit;
        }

        $this->view->hp = $hp;
        $this->view->assign('params', $params);
    }

    /**
     * Check account has privilege edit toporiginal
     * return bool
     */
    public function checkRedirectTopOriginal($row, $companyId, $admin, $agency) {
        $isPlan = !App_Model_List_Original::checkPlanCanUseTopOriginal($row->cms_plan);
        $redirect = false;
        $canOpenAgency = Custom_User_Admin::getInstance()->getProfile()->privilege_open_flg;
        $canCreateAgency = Custom_User_Admin::getInstance()->getProfile()->privilege_create_flg;

        if (($admin && !($canOpenAgency || $canCreateAgency)) || (!$admin && !$agency)) {
            $redirect = true;
        }
        if (!$row->checkTopOriginal() || $isPlan) {
            $redirect = true;
        }
        return $redirect;
    }

    /**
     * login agency show prewiew
     */
    public function loginForword($companyId){
        $this->getUser()->setAgency(new Custom_User_Agency);
        if(!$this->getUser()->getAgency()->getAdminProfile()){
            $this->getUser()->getAgency()->setAdminProfile($this->getUser()->getProfile());
        }
        $companyObj = App_Model_DbTable_Company::slave();
        $row = $companyObj->getDataForId($companyId);
        $this->getUser()->getAgency()->loginAgency($row->member_no);
    }
    
    /**
     * get params preview
     */
    public function getParamsPreviewAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $params = $this->getAllParams();
        $companyId = $this->getparam("company_id");
        $this->loginForword($companyId);
        $row = $this->_checkCompanyTOP($companyId);
        // Check expired contact or creatorHp exist
        if (!$row->isAvailable() || !$row->getCurrentCreatorHp()) {
            return;
        }
        $hp = $row->getCurrentCreatorHp();
        $data = array();
        $topPage = App_Model_DbTable_HpPage::slave()->getTopPageData($hp->id);
        if (isset($params['parts'])) {
            foreach ($params['parts'] as $id=>$value) {
                if ($value['display_flg'] == '1') {
                    $row = App_Model_DbTable_HpMainParts::slave()->fetchRow(array('id = ?'=>$id));
                    $value['special_id'] = $row->attr_1;
                    $data['koma'][$id] = $value;
                }
            }
        } else {
            $komaParts = App_Model_DbTable_HpMainParts::slave()->getPartsByType($topPage->id, App_Model_DbTable_HpMainParts::PARTS_ESTATE_KOMA);
            if ($komaParts) {
                foreach ($komaParts as $koma) {
                    $data['koma'][$koma->id]= array(
                        'display_flg' => '1',
                        'special_id' =>  $koma->attr_1,
                        'pc_columns' => $koma->attr_4,
                        'pc_columns_disable' => $koma->attr_5,
                        'pc_rows' => $koma->attr_6,
                        'pc_rows_disable' => $koma->attr_7,
                        'sp_columns' => $koma->attr_8,
                        'sp_columns_disable' => $koma->attr_9,
                        'sp_rows' => $koma->attr_10,
                        'sp_rows_disable' => $koma->attr_11,
                        'sort_option' => $koma->attr_3,
                    );
                }
            }
        }
        if (isset($params['settings'])) {
            foreach ($params['settings'] as $settings) {
                $data['notifications'][] = $settings;
            }
        }
        $data['page_id'] = $topPage->id;
        if (isset($params['navigation'])) {
            $data['navigation'] = $params['navigation']['global_navigation'];
        }
        return $this->_helper->json($data);
    }

    // Check Privilege Edit
    private function checkPrivilegeEdit($id) {
        $dataLogin = App_Model_DbTable_Manager::slave()->getDataForId($id);
        if ($dataLogin->privilege_edit_flg == 1) {
            return true;
        }
        return false;
    }
}


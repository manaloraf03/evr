<?php
	defined('BASEPATH') OR exit('direct script access is not allowed');

	class Petty_cash_journal extends CORE_Controller
	{
		function __construct()
		{
			parent::__construct('');
			$this->validate_session();
			$this->load->model(
				array(
					'Journal_info_model',
					'Journal_account_model',
					'Suppliers_model',
					'Departments_model',
					'Account_title_model',
					'Account_integration_model',
					'Batch_info_model',
					'Users_model',
					'Payment_method_model',
					'Tax_model',
					'Banks_model',
					'Trans_model'
				)
			);
		}

		public function index() {
			$data['_def_css_files'] = $this->load->view('template/assets/css_files', '', true);
	        $data['_def_js_files'] = $this->load->view('template/assets/js_files', '', true);
	        $data['_switcher_settings'] = $this->load->view('template/elements/switcher', '', true);
	        $data['_side_bar_navigation'] = $this->load->view('template/elements/side_bar_navigation', '', true);
	        $data['_top_navigation'] = $this->load->view('template/elements/top_navigation', '', true);
	        $data['title'] = 'Petty Cash Journal';
        	$data['payment_methods']=$this->Payment_method_model->get_list('is_deleted=0');
	        $data['suppliers']=$this->Suppliers_model->get_list(
	        	'is_deleted=FALSE AND is_active=TRUE'
	        );

	        $data['tax_types']=$this->Tax_model->get_list(array('tax_types.is_deleted'=>FALSE));

	        $data['departments']=$this->Departments_model->get_list(
	        	'is_deleted=FALSE AND is_active=TRUE'
	        );
	        $data['account_titles'] = $this->Account_title_model->get_list(array('is_deleted'=>FALSE));
	        $data['accounts']=$this->Account_title_model->get_list(
	        	'account_titles.is_deleted=FALSE AND account_titles.is_active=TRUE AND ac.account_type_id=5',
	        	'account_titles.account_title,
	        	account_titles.account_no,
	        	account_titles.account_id,
	        	ac.account_type_id',
	        	array(
	        		array('account_classes as ac','ac.account_class_id=account_titles.account_class_id','left')
	        	)
	        );
        $data['banks']=$this->Banks_model->get_list('is_deleted=FALSE');
        (in_array('1-6',$this->session->user_rights)? 
        $this->load->view('petty_cash_journal_view',$data)
        :redirect(base_url('dashboard')));
	        
		}

		function transaction($txn) {
			switch($txn) {
				case 'list':
					$m_journal=$this->Journal_info_model;

					$asOfDate=date('Y-m-d',strtotime($this->input->get('aod',TRUE)));
					$depid=$this->input->get('depid',TRUE);

					$response['data']=$m_journal->get_petty_cash_list($asOfDate,$depid);

					echo json_encode($response);
				break;

				case 'list-replenish':
					$m_journal=$this->Journal_info_model;

					$asOfDate=date('Y-m-d',strtotime($this->input->get('aod',TRUE)));
					$depid=$this->input->get('depid',TRUE);

					$response['data']=$m_journal->get_list(
						'journal_info.is_active=TRUE AND journal_info.is_deleted=FALSE AND journal_info.book_type="PCJ"',
						'journal_info.*, batch_info.*, CONCAT("replenished as of ", DATE_FORMAT(batch_info.date_replenished,"%m-%d-%Y")) AS date_covered',
						array(
							array('batch_info','batch_info.batch_id=journal_info.batch_id','inner'),
							array('departments','departments.department_id=journal_info.department_id','left')
						)
					);

					echo json_encode($response);
				break;

				case 'validate':
					$m_journal=$this->Journal_info_model;

					$AsOfDate=date('Y-m-d',strtotime($this->input->post('aod',TRUE)));
					$depid=$this->input->post('depid',TRUE);

					$remaining_amount=$m_journal->get_remaining_amount($AsOfDate,$depid);

					if($remaining_amount[0]->Balance == 0) {
						$response['title'] = "Error";
	                    $response['stat'] = "error";
	                    $response['msg'] = "Can't Post expense, petty cash amount must not be zero.";
					} else
						$response['stat'] = 'success';

                    echo json_encode($response);
				break;

				case 'validate-before-save':
					$m_journal=$this->Journal_info_model;

					$AsOfDate=date('Y-m-d',strtotime($this->input->post('aod',TRUE)));
					$depid=$this->input->post('depid',TRUE);
					$post_amount=$this->get_numeric_value($this->input->post('post_amount',TRUE));

					$remaining_amount=$m_journal->get_remaining_amount($AsOfDate,$depid);


					if($remaining_amount[0]->Balance == 0){
						$response['title'] = "Error";
	                    $response['stat'] = "error";
	                    $response['msg'] = "Can't Post expense, petty cash amount must not be zero in your chosen department.";
	                } else if($remaining_amount[0]->Balance < $post_amount) {
						$response['title'] = "Error";
	                    $response['stat'] = "error";
	                    $response['msg'] = "Can't Post expense, Expense amount must not exceed petty cash amount .";    
					}else{	$response['stat'] = 'success';
					}

                    echo json_encode($response);
				break;

				case 'save':
					$m_journal=$this->Journal_info_model;
					$m_accounts=$this->Journal_account_model;
					$m_account_integration=$this->Account_integration_model;

					$m_journal->begin();

					$m_journal->set('date_created','NOW()');
                	$ref_no_count = COUNT($m_journal->get_list(array('book_type'=>'PCJ')))+1;
                	$m_journal->ref_no='PCJ-'.str_pad($ref_no_count, 5, "0", STR_PAD_LEFT);
					// $m_journal->ref_no=$this->input->post('ref_no',TRUE);
					$m_journal->supplier_id=$this->input->post('supplier_id',TRUE);
					$m_journal->department_id=$this->input->post('department_id',TRUE);
					$m_journal->book_type='PCJ';
					$m_journal->date_txn=date('Y-m-d',strtotime($this->input->post('date_txn',TRUE)));
					$m_journal->amount=$this->get_numeric_value($this->input->post('amount',TRUE));
					$m_journal->created_by_user=$this->session->user_id;
					$m_journal->remarks=$this->input->post('remarks',TRUE);


					$m_journal->save();

					$journal_id=$m_journal->last_insert_id();

					$petty_cash_id=$m_account_integration->get_list(
						null,
						'petty_cash_account_id'
					);

					$m_accounts->journal_id=$journal_id;
					$m_accounts->account_id=$this->input->post('account_id',TRUE);
					$m_accounts->dr_amount=$this->get_numeric_value($this->input->post('amount',TRUE));
					$m_accounts->save();

					$petty_cash_account_id=$petty_cash_id[0]->petty_cash_account_id;
					$m_accounts->journal_id=$journal_id;
					$m_accounts->account_id=$petty_cash_account_id;
					$m_accounts->dr_amount=$this->get_numeric_value('0');
					$m_accounts->cr_amount=$this->get_numeric_value($this->input->post('amount',TRUE));
					$m_accounts->save();

					$m_journal->txn_no='PCJ-'.date('Ymd').'-'.$journal_id;
					$m_journal->modify($journal_id);

	                // AUDIT TRAIL START

	                $m_trans=$this->Trans_model;
	                $m_trans->user_id=$this->session->user_id;
	                $m_trans->set('trans_date','NOW()');
	                $m_trans->trans_key_id=1; //CRUD
	                $m_trans->trans_type_id=5; // TRANS TYPE
	                $m_trans->trans_log='Posted Petty Cash Journal Entry PCJ-'.date('Ymd').'-'.$journal_id;
	                $m_trans->save();
	                //AUDIT TRAIL END

					$m_journal->commit();

					$response['title'] = 'Success!';
                    $response['stat'] = 'success';
                    $response['msg'] = 'Petty Cash successfully created.';
                    $response['row_added']=$this->response_rows($journal_id);

                    echo json_encode($response);
				break;

				case 'update':
					$m_journal=$this->Journal_info_model;
					$m_accounts=$this->Journal_account_model;
					$m_account_integration=$this->Account_integration_model;

					$journal_id=$this->input->post('journal_id',TRUE);

					$m_journal->begin();

					// $m_journal->ref_no=$this->input->post('ref_no',TRUE);
					$m_journal->supplier_id=$this->input->post('supplier_id',TRUE);
					$m_journal->department_id=$this->input->post('department_id',TRUE);
					$m_journal->book_type='PCJ';
					$m_journal->date_txn=date('Y-m-d',strtotime($this->input->post('date_txn',TRUE)));
					$m_journal->amount=$this->get_numeric_value($this->input->post('amount',TRUE));
					$m_journal->remarks=$this->input->post('remarks',TRUE);
					$m_journal->modify($journal_id);

					$m_accounts->delete_via_fk($journal_id);

					$petty_cash_id=$m_account_integration->get_list(
						null,
						'petty_cash_account_id'
					);

					$m_accounts->journal_id=$journal_id;
					$m_accounts->account_id=$this->input->post('account_id',TRUE);
					$m_accounts->dr_amount=$this->get_numeric_value($this->input->post('amount',TRUE));
					$m_accounts->save();

					$petty_cash_account_id=$petty_cash_id[0]->petty_cash_account_id;
					$m_accounts->journal_id=$journal_id;
					$m_accounts->account_id=$petty_cash_account_id;
					$m_accounts->dr_amount=$this->get_numeric_value('0');
					$m_accounts->cr_amount=$this->get_numeric_value($this->input->post('amount',TRUE));
					$m_accounts->save();

					$m_journal->commit();
                // AUDIT TRAIL START

                $m_trans=$this->Trans_model;
                $m_trans->user_id=$this->session->user_id;
                $m_trans->set('trans_date','NOW()');
                $m_trans->trans_key_id=2; //CRUD
                $m_trans->trans_type_id=5; // TRANS TYPE
                $m_trans->trans_log='Updated Petty Cash Journal Entry PCJ-'.date('Ymd').'-'.$journal_id;
                $m_trans->save();
                //AUDIT TRAIL END
					$response['title'] = 'Success!';
                    $response['stat'] = 'success';
                    $response['msg'] = 'Petty Cash successfully updated.';
                    $response['row_updated']=$this->response_rows($journal_id);

                    echo json_encode($response);
				break;

				case 'cancel':
					$m_journal=$this->Journal_info_model;

					$journal_id=$this->input->post('journal_id',TRUE);

					$m_journal->is_active=FALSE;
					$m_journal->set('date_cancelled','NOW()');
					$m_journal->cancelled_by_user=$this->session->user_id;
					$m_journal->modify($journal_id);
		            $journal_txn_no =$m_journal->get_list($journal_id,'txn_no,is_active');
		            $m_trans=$this->Trans_model;
		            $m_trans->user_id=$this->session->user_id;
		            $m_trans->set('trans_date','NOW()');
		            $m_trans->trans_key_id=4; //CRUD
		            $m_trans->trans_type_id=5; // TRANS TYPE
		            $m_trans->trans_log='Cancelled Petty Cash Journal Entry : '.$journal_txn_no[0]->txn_no;
		            $m_trans->save();
					$response['title'] = 'Success!';
                    $response['stat'] = 'success';
                    $response['msg'] = 'Petty Cash successfully cancelled.';

                    echo json_encode($response);
				break;

				case 'get-totals':
					$m_journal=$this->Journal_info_model;

					$AsOfDate=date('Y-m-d',strtotime($this->input->get('aod',TRUE)));
					$depid=$this->input->get('depid',TRUE);

					$remaining_amount=$m_journal->get_remaining_amount($AsOfDate,$depid);

					$unreplenished_expense=$m_journal->get_list(
						'is_replenished=0
						AND book_type="PCJ"
						AND is_active=TRUE
						AND is_deleted=FALSE
						AND date_txn <= "'.$AsOfDate.'"'.
						($depid==1 ? "" : " AND department_id=".$depid),
						'SUM(amount) AS unreplenished_expense'
					);

					$response['unreplenished_expense']=number_format($unreplenished_expense[0]->unreplenished_expense,2);
					$response['remaining_amount']=number_format($remaining_amount[0]->Balance,2);

					echo json_encode($response);
				break;

				case 'replenish':

					$m_journal=$this->Journal_info_model;
					$m_account_integration=$this->Account_integration_model;
					$AsOfDate=date('Y-m-d',strtotime($this->input->post('aod',TRUE)));
					$depid=$this->input->post('depid',TRUE);

					$unreplenished_expense=$m_journal->get_list(
						'is_replenished=0
						AND book_type="PCJ"
						AND is_active=TRUE
						AND is_deleted=FALSE
						AND date_txn <= "'.$AsOfDate.'"'.
						($depid==1 ? "" : " AND department_id=".$depid),
						'IFNULL(SUM(amount),0) AS unreplenished_expense'
					);

					$unreplenished_value = $unreplenished_expense[0]->unreplenished_expense;

					if ($unreplenished_value == '0') {
						$response['title'] = 'Error!';
	                    $response['stat'] = 'error';
	                    $response['msg'] = "Cannot replenish petty cash, no active transactions to replenish ";

	                    die(json_encode($response));
					} else {
	                	$m_journal->begin();

						$m_batch=$this->Batch_info_model;

	                	$m_batch->set('date_replenished','NOW()');
	                	$m_batch->replenished_by=$this->session->user_id;
	                	$m_batch->save();

	                	$batch_id=$m_batch->last_insert_id();

	                	$m_batch->batch_no='PCJB-'.date('Ymd').'-'.$batch_id;
	                	$m_batch->modify($batch_id);
	                	$m_journal->is_replenished=TRUE;
	                	$m_journal->batch_id=$batch_id;
						$m_journal->modify('book_type="PCJ" AND date_txn <= "'.$AsOfDate.'" AND department_id='.$depid.' AND is_replenished=FALSE');

						$m_journal->department_id=$depid;
						$m_journal->bank_id=$this->input->post('bank_id',TRUE);
						$m_journal->supplier_id=$this->input->post('supplier_id');
						$m_journal->remarks= 'To Replenish Petty Cash on or before '. $AsOfDate;
						$m_journal->book_type='CDJ';
		                $m_journal->created_by_user=$this->session->user_id;
		                $m_journal->payment_method_id =$this->input->post('payment_method');
		                if($this->input->post('payment_method')){
			                $m_journal->check_no =$this->input->post('check_no');
			                $m_journal->check_date = date('Y-m-d',strtotime($this->input->post('check_date',TRUE)));		                
		            	}
		                $m_journal->amount=$this->get_numeric_value($unreplenished_value);
						$m_journal->set('date_txn','NOW()');
						$m_journal->set('date_created','NOW()');

		                $ref_type = 'CV';
		                $m_journal->ref_type = 'CV';
		                $account_integration=$m_account_integration->get_list();
		                if($ref_type == 'CV'){
		                    $ref_type_count = COUNT($m_journal->get_list(array('ref_type'=>$ref_type)))+1+ $account_integration[0]->cv_start_no;
		                }else{
		                    $ref_type_count = COUNT($m_journal->get_list(array('ref_type'=>$ref_type)))+1 + $account_integration[0]->jv_start_no;
		                }
		 				$m_journal->ref_no=str_pad($ref_type_count, 8, "0", STR_PAD_LEFT);
		                $m_journal->save();

		                $journal_id=$m_journal->last_insert_id();

		                $m_journal->txn_no='TXN-'.date('Ymd').'-'.$journal_id;

		                $m_journal->modify($journal_id);

		                $m_accounts=$this->Journal_account_model;

		                $m_accounts->journal_id=$journal_id;
						
						$petty_cash_id=$m_account_integration->get_list(null,'petty_cash_account_id');
						$m_accounts->account_id=$petty_cash_id[0]->petty_cash_account_id;


						// $m_accounts->account_id=13;
						$m_accounts->dr_amount=$this->get_numeric_value($unreplenished_value);
						$m_accounts->save();

						$m_accounts->journal_id=$journal_id;
						$m_accounts->account_id=$this->input->post('account_id_credit');
						$m_accounts->dr_amount=$this->get_numeric_value('0');
						$m_accounts->cr_amount=$this->get_numeric_value($unreplenished_value);
						$m_accounts->save();

		                $m_journal->commit();

						$response['title'] = 'Success!';
	                    $response['stat'] = 'success';
	                    $response['msg'] = 'Petty Cash successfully replenished.';

		                // AUDIT TRAIL START

		                $m_trans=$this->Trans_model;
		                $m_trans->user_id=$this->session->user_id;
		                $m_trans->set('trans_date','NOW()');
		                $m_trans->trans_key_id=8; //CRUD
		                $m_trans->trans_type_id=5; // TRANS TYPE
		                $m_trans->trans_log='Replenished Petty Cash from transactions on or before '.$AsOfDate.' and Posted Journal Entry TXN-'.date('Ymd').'-'.$journal_id;
		                $m_trans->save();
		                //AUDIT TRAIL END



	                    echo json_encode($response);
                    }
				break;
			}
		}

		function response_rows($filter=null){
			return $this->Journal_info_model->get_list(
				array(
					'journal_info.is_deleted'=>FALSE,
					'journal_info.is_active'=>TRUE,
					'journal_info.book_type'=>'PCJ',
					'journal_info.journal_id'=>$filter,
					'account_classes.account_type_id=5'
				),
					'journal_info.journal_id,
					journal_info.txn_no,
					journal_info.department_id,
					departments.department_name,
					journal_info.supplier_id,
					journal_info.remarks,
					journal_info.book_type,
					DATE_FORMAT(journal_info.date_txn,"%m/%d/%Y") AS date_txn,
					journal_info.ref_no,
					journal_info.amount,
					journal_info.or_no,
					journal_info.is_replenished,
					suppliers.*,
					journal_accounts.*',
				array(
					array('suppliers','suppliers.supplier_id=journal_info.supplier_id','left'),
					array('departments','departments.department_id=journal_info.department_id','left'),
					array('journal_accounts','journal_accounts.journal_id=journal_info.journal_id','inner'),
					array('account_titles','account_titles.account_id=journal_accounts.account_id','left'),
					array('account_classes','account_classes.account_class_id=account_titles.account_class_id','left')
				)
			);
		}
 	}
?>

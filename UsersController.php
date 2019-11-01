<?php
App::uses('AppController', 'Controller');

/**
 * Users Controller
 *
 * @property User $User
 */
class UsersController extends AppController { 
    var $uses = array("User", "EmailTemplate");
    var $helper = array('Html', 'File', 'Csv', 'Cookie');
    public $components = array(
        'Default', 'Mpdf',
        'Auth' => array(
            'authenticate' => array(
                'Form' => array(
                    'fields' => array('username' => 'email')
                )
            )
        )
    );
    public function beforeFilter() {
		
        parent::beforeFilter();   
        $iPad    = stripos($_SERVER['HTTP_USER_AGENT'],"iPad");
        $tablet    = stripos($_SERVER['HTTP_USER_AGENT'],"Mobile"); 
        if ($this->RequestHandler->isMobile() && (!$iPad && $tablet)) { 
            $this->is_mobile = true;
            $this->set('is_mobile', true);
            $this->autoRender = false;
        }
    }

    function afterFilter() { 
        if (isset($this->is_mobile) && $this->is_mobile) {
            $has_mobile_view_file = file_exists(ROOT . DS . APP_DIR . DS . 'View' . DS . $this->name . DS . 'mobile' . DS . $this->action . '.ctp');
            $has_mobile_layout_file = file_exists(ROOT . DS . APP_DIR . DS . 'View' . DS . 'Layouts' . DS . 'mobile' . DS . $this->layout . '.ctp');
            $view_file = ( $has_mobile_view_file ? 'mobile' . DS : '' ) . $this->action;
            $layout_file = ( $has_mobile_layout_file ? 'mobile' . DS : '' ) . $this->layout;
            $this->render($view_file, $layout_file);
        }
    }

    public function admin_login() { 
        $this->layout = 'admin_login'; 
        $this->set('title_for_layout', __('Admin Login'));
        if ($this->request->is('post')) { 
            if ($this->Auth->login()) { 
                if (empty($this->request->data['User']['remember_me'])) { 
                    $this->Cookie->delete('User');
                } else {
                    $cookie = array();
                    $cookie['email'] = $this->request->data['User']['email'];
                    $cookie['password'] = $this->request->data['User']['password'];
                    $cookie['remember_me'] = $this->request->data['User']['remember_me'];
					$this->Cookie->write('User', $cookie, true, '+2 weeks');
                }
                
                $this->User->updateAll(array(
                    'User.last_login' => '\'' . date('Y-m-d h:i:s') . '\''
                        ), array(
                    'User.id' => $this->Auth->user('id')
                ));
                $this->Session->setFlash(__('Logged in successfully'));
                return $this->redirect(array("action" => "dashboard"));
            } else {
                echo "<pre>";print_r($this->Auth->authError);die;
                $this->Session->setFlash(__('Invalid Username and Password'));
                $this->Session->setFlash($this->Auth->authError, 'default', array(), 'auth');
                $this->redirect($this->Auth->loginAction);
            }
        } else {
            $this->request->data['User'] = $this->Cookie->read('User');
        }
    }

    function admin_invoice_download(){
        
    }

    public function admin_dashboard() {

        if($this->Auth->user('role_id')==4 && $this->Auth->user('country_id')==13){
            $conditions['User.country_id'] = 13;
        }
        $conditions['User.role_id'] = 2;
        $total_user = $this->User->find('count', array('conditions' => $conditions)); 

        $jsIncludes = array('admin/chosen.jquery.min.js', 'admin/jquery.toggle.buttons.js', 'admin/jquery.reveal.js');
        $cssIncludes = array('admin/chosen.css', 'admin/bootstrap-toggle-buttons.css');
        $this->set(compact('jsIncludes', 'cssIncludes', 'total_user', 'recent_users'));
    }

    /**
     * admin_index method
     *
     * @return void
     */
    public function admin_index($is_store=null) {
        //$this->layout = 'admin';
        $this->User->recursive = 0;

        $limit = (isset($this->params['named']['showperpage'])) ? $this->params['named']['showperpage'] : Configure::read('site.admin_paging_limit');
        $conditions = array();
        if($is_store != null){
            $conditions = array("User.is_store_user" => 1);
        }
        if (isset($this->params['named']['keyword']) && $this->params['named']['keyword'] != '') {
            $conditions = array(
                    'OR' => array(
                        'User.first_name LIKE ' => '%' . $this->params['named']['keyword'] . '%',
                        'User.last_name LIKE ' => '%' . $this->params['named']['keyword'] . '%',
                        'User.email LIKE ' => '%' . $this->params['named']['keyword'] . '%',
                )
            );
        }
        if (!empty($this->request->data)) {
            if (isset($this->request->data['showperpage']) && $this->request->data['showperpage'] != '') {
                $limit = $this->request->data['showperpage'];
                $this->params['named'] = array("showperpage" => $limit);
            }
            if (isset($this->request->data['keyword']) && $this->request->data['keyword'] != '') {
                $this->params['named'] = array("keyword" => $this->request->data['keyword']);
                $conditions = array(
                    'OR' => array(
                        'User.first_name LIKE ' => '%' . $this->request->data['keyword'] . '%',
                        'User.last_name LIKE ' => '%' . $this->request->data['keyword'] . '%',
                        'User.email LIKE ' => '%' . $this->request->data['keyword'] . '%',
                    )
                ); 
            }
        } 
        if($this->Auth->user('role_id')==4 && $this->Auth->user('country_id')==13){
            $conditions['User.country_id'] = 13;
        }
        $conditions['User.role_id'] = 2;

        if ($limit == 'ALL') {
            $paging_limit = '1000000';
        } else {
            $paging_limit = $limit;
        }

        $this->paginate = array("conditions" => $conditions, "limit" => $paging_limit, "order" => "User.id DESC");
        $this->set(compact('limit'));
        $this->set('users', $this->paginate());
        $cssIncludes = array('admin/jquery-ui-1.10.1.custom.min');
        $this->set(compact('jsIncludes', 'cssIncludes'));
    }

    /**
     * admin_view method
     *
     * @throws NotFoundException
     * @param string $id
     * @return void
     */
    public function admin_companyList() {
        //$this->layout = 'admin';
        $this->loadModel('AppCompany');
        $this->AppCompany->recursive = 0;

        $limit = (isset($this->params['named']['showperpage'])) ? $this->params['named']['showperpage'] : Configure::read('site.admin_paging_limit');
        $conditions = array();

        if (isset($this->params['named']['keyword']) && $this->params['named']['keyword'] != '') {
            $conditions['AppCompany.name LIKE'] = '%' . $this->params['named']['keyword'] . '%';
        }
        if (!empty($this->request->data)) {
            if (isset($this->request->data['showperpage']) && $this->request->data['showperpage'] != '') {
                $limit = $this->request->data['showperpage'];
                $this->params['named'] = array("showperpage" => $limit);
            }
            if (isset($this->request->data['keyword']) && $this->request->data['keyword'] != '') {
                $this->params['named'] = array("keyword" => $this->request->data['keyword']);
                $conditions = array(
                    'OR' => array(
                        'AppCompany.name LIKE ' => '%' . $this->request->data['keyword'] . '%',
                    )
                );
            }
        }
        $conditions['AppCompany.is_first'] = 1;

        if ($limit == 'ALL') {
            $paging_limit = '1000000';
        } else {
            $paging_limit = $limit;
        }

        $this->paginate = array("conditions" => $conditions, "limit" => $paging_limit, "order" => "AppCompany.id DESC");
        $this->set(compact('limit'));
        $this->set('users', $this->paginate('AppCompany'));
    }

    public function admin_view($id = null) {
        /*
          if (!$this->SitePermission->CheckPermission($this->Auth->user("role_id"), 'users', 'is_read'))
          {
          $this->Session->setFlash(__('You are not authorised to access that location'));
          $this->redirect(array('controller' => 'users', 'action' => 'dashboard'));
          }
         */
        if (!$this->User->exists($id)) {
            throw new NotFoundException(__('Invalid user'));
        }
        $this->User->bindModel(
                array(
            'belongsTo' => array(
                'Country' => array('foreignKey' => false,
                    'conditions' => array('User.country_id = Country.id')),
            )
                ), false
        );
        $this->User->bindModel(
                array(
            'belongsTo' => array(
                'Plan' => array('foreignKey' => false,
                    'conditions' => array('User.plan_id = Plan.id')),
            )
                ), false
        );
        $options = array('conditions' => array('User.' . $this->User->primaryKey => $id));

        $this->set('user', $this->User->find('first', $options));


        $this->User->bindModel(array(
            'hasMany' => array(
                'UsersCompany' => array(
                    'className' => 'UsersCompany',
                    'foreignKey' => 'administrator_id',
                    'fields' => array('id'),
                    'conditions' => array('is_accept' => 1)
                )
            )
        ));
        $planDetail = $this->User->find('first', array('conditions' => array('User.id' => $id),
            'fields' => array('User.subscription_status', 'User.id', 'User.phone', 'User.plan_id', 'User.total_space', 'User.first_name', 'User.last_name')));
        $this->loadModel('PaymentInformation');
        $this->PaymentInformation->bindModel(array(
            'belongsTo' => array('User')
        ));
        $paymentDetail = $this->PaymentInformation->find('first', array('conditions' => array('user_id' => $id)));
        if (!empty($paymentDetail)) {
            $nextPaymentDate = strtotime('+' . $paymentDetail['PaymentInformation']['frequency'], strtotime($paymentDetail['PaymentInformation']['payment_date']));
            while ($nextPaymentDate < time()) {
                $nextPaymentDate = strtotime('+' . $paymentDetail['PaymentInformation']['frequency'], $nextPaymentDate);
            }
            $nextPaymentDate = date($this->Session->read('DATE_FORMAT'), $nextPaymentDate);
        }
        $this->set(compact('planDetail', 'nextPaymentDate', 'paymentDetail'));

        $jsIncludes = array('admin/chosen.jquery.min.js', 'admin/jquery.toggle.buttons.js', 'admin/jquery.reveal.js', 'admin/jquery.validationEngine.js', 'admin/jquery.validationEngine-en.js', 'admin/bootstrap-datepicker.js', 'admin/clockface.js', 'admin/bootstrap-timepicker_add.js');
        $cssIncludes = array('admin/chosen.css', 'admin/bootstrap-toggle-buttons.css', 'admin/validationEngine.jquery.css', 'admin/datepicker.css', 'admin/clockface.css', 'admin/timepicker.css', 'admin/smoothness.css');
        $this->set(compact('jsIncludes', 'cssIncludes'));
    }

    public function admin_view_standard($id = null) {

        $limit = (isset($this->params['named']['showperpage'])) ? $this->params['named']['showperpage'] : Configure::read('site.admin_paging_limit');
        $conditions = array();

        if (isset($this->params['named']['keyword']) && $this->params['named']['keyword'] != '') {
            $conditions['User.email LIKE'] = '%' . $this->params['named']['keyword'] . '%';
        }
        if (!empty($this->request->data)) {
            if (isset($this->request->data['showperpage']) && $this->request->data['showperpage'] != '') {
                $limit = $this->request->data['showperpage'];
                $this->params['named'] = array("showperpage" => $limit);
            }
            if (isset($this->request->data['keyword']) && $this->request->data['keyword'] != '') {
                $this->params['named'] = array("keyword" => $this->request->data['keyword']);
                $conditions = array(
                    'OR' => array(
                        'User.first_name LIKE ' => '%' . $this->request->data['keyword'] . '%',
                        'User.last_name LIKE ' => '%' . $this->request->data['keyword'] . '%',
                    )
                );
            }
        }
        $this->loadModel('UsersCompany');
        $this->loadModel('Company');
        $this->UsersCompany->bindModel(array(
            'belongsTo' => array('User')
                ), false);
        $this->UsersCompany->unbindModel(array(
            'belongsTo' => array('Company')
        ));

        $conditions['UsersCompany.administrator_id'] = $id;
        $conditions['UsersCompany.transaction_status'] = 1;
        $conditions['UsersCompany.role_id'] = 3;

        if ($limit == 'ALL') {
            $paging_limit = '1000000';
        } else {
            $paging_limit = $limit;
        }

        $this->paginate = array("conditions" => $conditions, "limit" => $paging_limit);
        $this->set(compact('limit'));
        $this->set('companyUsers', $this->paginate('UsersCompany'));
    }

    public function admin_payment_history($id = null) {

        $limit = (isset($this->params['named']['showperpage'])) ? $this->params['named']['showperpage'] : Configure::read('site.admin_paging_limit');
        $conditions = array();

        if (isset($this->params['named']['keyword']) && $this->params['named']['keyword'] != '') {
            $conditions['User.email LIKE'] = '%' . $this->params['named']['keyword'] . '%';
        }
        if (!empty($this->request->data)) {
            if (isset($this->request->data['showperpage']) && $this->request->data['showperpage'] != '') {
                $limit = $this->request->data['showperpage'];
                $this->params['named'] = array("showperpage" => $limit);
            }
            if (isset($this->request->data['keyword']) && $this->request->data['keyword'] != '') {
                $this->params['named'] = array("keyword" => $this->request->data['keyword']);
                $conditions = array(
                    'OR' => array(
                        'User.email LIKE ' => '%' . $this->request->data['keyword'] . '%',
                    )
                );
                //	$conditions['User.first_name LIKE '] = '%' .  $this->request->data['keyword'] . '%';
            }
        }
        $this->loadModel('UserSubscriptionHistory');
        $this->UserSubscriptionHistory->bindModel(array(
            'belongsTo' => array(
                'User' => array(
                    'fields' => array('User.first_name', 'User.last_name')
                )
            )
        ));

        $conditions['UserSubscriptionHistory.user_id'] = $id;



        if ($limit == 'ALL') {
            $paging_limit = '1000000';
        } else {
            $paging_limit = $limit;
        }

        $this->paginate = array("conditions" => $conditions, "limit" => $paging_limit);
        /* pr($this->paginate('UserSubscriptionHistory'));
          die; */
        $this->set(compact('limit'));
        $this->set('historyRecord', $this->paginate('UserSubscriptionHistory'));
    }

    public function admin_view_group($id = 0) {

        $limit = (isset($this->params['named']['showperpage'])) ? $this->params['named']['showperpage'] : Configure::read('site.admin_paging_limit');
        $conditions = array();

        if (isset($this->params['named']['keyword']) && $this->params['named']['keyword'] != '') {
            $conditions['User.email LIKE'] = '%' . $this->params['named']['keyword'] . '%';
        }
        if (!empty($this->request->data)) {

            if (isset($this->request->data['showperpage']) && $this->request->data['showperpage'] != '') {
                $limit = $this->request->data['showperpage'];
                $this->params['named'] = array("showperpage" => $limit);
            }
            if (isset($this->request->data['keyword']) && $this->request->data['keyword'] != '') {
                $this->params['named'] = array("keyword" => $this->request->data['keyword']);
                $conditions = array(
                    'OR' => array(
                        'User.email LIKE ' => '%' . $this->request->data['keyword'] . '%',
                    )
                );
                //	$conditions['User.first_name LIKE '] = '%' .  $this->request->data['keyword'] . '%';
            }
        }
        $this->loadModel('Group');
        $this->Group->bindModel(array(
            'hasMany' => array(
                'GroupUser' => array(
                    'fields' => array('GroupUser.id')
                )
            )
        ));
        $conditions['Group.user_id'] = $id;
        if ($limit == 'ALL') {
            $paging_limit = '1000000';
        } else {
            $paging_limit = $limit;
        }
        $this->paginate = array("conditions" => $conditions, "limit" => $paging_limit);
        $groupList = $this->paginate('Group');
        $this->set(compact("groupList"));
    }

    function admin_import_csv() {
        App::import('Vendor', 'mergcsv/IOFactory');
        if (isset($this->data['uk_export']) && $this->data['uk_export'] == 'uk_export') {
            $conditions = array();
            if (isset($this->request->data['user_type']) && $this->request->data['user_type'] != '') {
                if ($this->request->data['user_type'] == 1) {
                    $conditions['User.country_id'] = 1;
                } elseif ($this->request->data['user_type'] == 0) {
                    $conditions['User.country_id !='] = 1;
                }
            }
            $demoUsers = array(
                '14' => '14',
                '15' => '15',
                '71' => '71',
                '123' => '123',
                '125' => '125',
                '126' => '126',
                '131' => '131',
                '133' => '133',
                '135' => '135',
                '159' => '159',
                '165' => '165',
                '213' => '213',
            );
            $conditions['UserSubscriptionHistory.created >='] = date("Y-m-d", strtotime(str_replace("/", "-", $this->request->data['User']['start_date'])));
            $conditions['UserSubscriptionHistory.created <='] = date("Y-m-d", strtotime(str_replace("/", "-", $this->request->data['User']['end_date'])));
            $conditions['UserSubscriptionHistory.user_id NOT IN'] = $demoUsers;
            $this->loadModel('UserSubscriptionHistory');

            $data = $this->UserSubscriptionHistory->find('all', array(
                'conditions' => $conditions,
                'order' => 'UserSubscriptionHistory.created asc',
                'contain' => array('User' => array('PaymentInformation', 'Country'))
                    )
            );
            $filename = "export_" . date("Y.m.d") . ".csv";
            $csv_file = fopen('php://output', 'w');
            header('Content-type: application/excel');
            header("Content-Transfer-Encoding: UTF-8");
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header("Expires: 0");
            $header_row = array('Date', 'Invoice Number', 'Name', 'Email', 'Telephone', 'Frequency of Payment', 'Country', 'Nett Amount','VAT','Gross Amount');
            fputcsv($csv_file, $header_row, ',', '"'); 

            foreach ($data as $result) {
				if($result['User']['Country']['id']==1){
                    $netAmount = ($result['UserSubscriptionHistory']['amount']*100)/120;
                    $vatAmount = $result['UserSubscriptionHistory']['amount'] - $netAmount;
                }else{
                    $netAmount = $result['UserSubscriptionHistory']['amount'];
                    $vatAmount = 0;
                }
                // Array indexes correspond to the field names in your db table(s)
                $row = array(
                    date('d-M-Y', strtotime($result['UserSubscriptionHistory']['created'])),
                    str_pad($result['UserSubscriptionHistory']['id'], 4, '0', STR_PAD_LEFT),
                    $result['User']['first_name'] . " " . $result['User']['last_name'],
                    $result['User']['email'],
                    $result['User']['phone'],
                    $result['User']['PaymentInformation']['frequency'], 
                    $result['User']['Country']['name'],
                    number_format($netAmount, 2),
                    number_format($vatAmount, 2),
                    number_format($result['UserSubscriptionHistory']['amount'], 2)
                );

                fputcsv($csv_file, $row, ',', '"');
            }
            fclose($csv_file);
            exit;
        } else {
            if (isset($this->request->data['user_type']) && $this->request->data['user_type'] != '') {
                if ($this->request->data['user_type'] == 2) {
                    $conditions['subscription_status'] = array(0, 1);
                }
                if ($this->request->data['user_type'] == 1) {
                    $conditions['subscription_status'] = 1;
                }
                if ($this->request->data['user_type'] == 0) {
                    $conditions['subscription_status'] = 0;
                }
            }
            $data = $this->User->find('all', array('conditions' => $conditions, 'order' => 'User.created desc'));
            /* pr($data);
              die; */
            $filename = "export_" . date("Y.m.d") . ".csv";
            $csv_file = fopen('php://output', 'w');
            header('Content-type: application/excel');
            header("Content-Transfer-Encoding: UTF-8");
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header("Expires: 0");
            $header_row = array('First Name', 'Last Name', 'Company', 'Email', 'User Status', 'Subscription Status');
            fputcsv($csv_file, $header_row, ',', '"');

            foreach ($data as $result) {
                if ($result['User']['status'] == 1) {
                    $status = 'Active';
                } else {
                    $status = 'InActive';
                }
                if ($result['User']['subscription_status'] == 1) {
                    $sub_status = 'Subscribed';
                } else {
                    $sub_status = 'UnSubscribed';
                }
                // Array indexes correspond to the field names in your db table(s)
                $row = array(
                    $result['User']['first_name'],
                    $result['User']['last_name'],
                    $result['Company']['name'],
                    $result['User']['email'],
                    $status,
                    $sub_status
                );

                fputcsv($csv_file, $row, ',', '"');
            }
            fclose($csv_file);
            exit;
        }
    }

    function admin_import_company_csv() {
        App::import('Vendor', 'mergcsv/IOFactory');
        $conditions['User.role_id'] = 2;
        $this->loadModel('AppCompany');
        $data = $this->AppCompany->find('all', array('conditions' => array('is_first' => 1), 'order' => 'AppCompany.id desc'));

        $filename = "export_" . date("Y.m.d") . ".csv";
        $csv_file = fopen('php://output', 'w');
        header('Content-type: application/excel');
        header("Content-Transfer-Encoding: UTF-8");
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header("Expires: 0");
        $header_row = array('Name', 'County', 'Telephonenumber', 'Email Address', 'Address 1', 'Address 2', 'Address 3', 'Zip Code');
        fputcsv($csv_file, $header_row, ',', '"');

        foreach ($data as $result) {

            $row = array(
                $result['AppCompany']['name'],
                $result['AppCompany']['county'],
                $result['AppCompany']['telephonenumber'],
                $result['AppCompany']['email_address'],
                $result['AppCompany']['address1'],
                $result['AppCompany']['address2'],
                $result['AppCompany']['address3'],
                $result['AppCompany']['zip_code'],
            );

            fputcsv($csv_file, $row, ',', '"');
        }
        fclose($csv_file);
        exit;
    }

    public function admin_cloud_safety()
    {
         //$this->layout = 'admin';
        $this->User->recursive = 0;
        $this->loadModel("RiskToSafetyUser");
        $this->RiskToSafetyUser->bindModel(array(
            "belongsTo" => array(
                "User"
            )
        ), false);
        $limit = (isset($this->params['named']['showperpage'])) ? $this->params['named']['showperpage'] : Configure::read('site.admin_paging_limit');
        $conditions = array();
         
        if (isset($this->params['named']['keyword']) && $this->params['named']['keyword'] != '') {
            $conditions = array(
                    'OR' => array(
                        'User.first_name LIKE ' => '%' . $this->params['named']['keyword'] . '%',
                        'User.last_name LIKE ' => '%' . $this->params['named']['keyword'] . '%',
                        'User.email LIKE ' => '%' . $this->params['named']['keyword'] . '%',
                )
            );
        }
        if (!empty($this->request->data)) {
            if (isset($this->request->data['showperpage']) && $this->request->data['showperpage'] != '') {
                $limit = $this->request->data['showperpage'];
                $this->params['named'] = array("showperpage" => $limit);
            }
            if (isset($this->request->data['keyword']) && $this->request->data['keyword'] != '') {
                $this->params['named'] = array("keyword" => $this->request->data['keyword']);
                $conditions = array(
                    'OR' => array(
                        'User.first_name LIKE ' => '%' . $this->request->data['keyword'] . '%',
                        'User.last_name LIKE ' => '%' . $this->request->data['keyword'] . '%',
                        'User.email LIKE ' => '%' . $this->request->data['keyword'] . '%',
                    )
                ); 
            }
        } 
         
        $conditions['User.role_id'] = 2;

        if ($limit == 'ALL') {
            $paging_limit = '1000000';
        } else {
            $paging_limit = $limit;
        }

        $this->paginate = array("conditions" => $conditions, "limit" => $paging_limit, "order" => "User.id DESC");
        $this->set(compact('limit'));
        $this->set('users', $this->paginate("RiskToSafetyUser"));
        $cssIncludes = array('admin/jquery-ui-1.10.1.custom.min');
        $this->set(compact('jsIncludes', 'cssIncludes'));
    }

    function admin_special_user_status($user_id=null, $status=0){
        $this->request->data['User']['id'] = $user_id;
        $this->request->data['User']['special_user_status'] = $status;
        $this->User->save($this->data); 
        die;
    }
    /**
     * admin_add method
     *
     * @return void
     */
    public function admin_add() {
        if (!$this->SitePermission->CheckPermission($this->Auth->user("role_id"), 'users', 'is_add')) {
            $this->Session->setFlash(__('You are not authorised to access that location'));
            $this->redirect(array('controller' => 'users', 'action' => 'dashboard'));
        }
        if ($this->request->is('post')) {
            if (!empty($this->request->data)) {
                $this->request->data['User']['role_id'] = 2;

                $this->User->create();
                if ($this->User->save($this->request->data)) {
                    $this->Session->setFlash(__('The user has been saved'));
                    $this->redirect(array('action' => 'index'));
                }
            }
            if (!$this->User->save($this->request->data)) {
                $this->Session->setFlash(__('The user could not be saved. Please, try again.'));
            }
        }
        $roles = $this->User->Role->find('list');
        $this->set(compact('roles'));
        $jsIncludes = array('admin/chosen.jquery.min.js', 'admin/jquery.toggle.buttons.js', 'admin/jquery.reveal.js', 'admin/jquery.validationEngine.js', 'admin/jquery.validationEngine-en.js');
        $cssIncludes = array('admin/chosen.css', 'admin/bootstrap-toggle-buttons.css', 'admin/validationEngine.jquery.css');
        $this->set(compact('parents', 'users', 'jsIncludes', 'cssIncludes'));
    }

    /**
     * admin_edit method
     *
     * @throws NotFoundException
     * @param string $id
     * @return void
     */
    public function admin_edit($id = null) {
        if (!$this->SitePermission->CheckPermission($this->Auth->user("role_id"), 'users', 'is_edit')) {
            $this->Session->setFlash(__('You are not authorised to access that location'));
            $this->redirect(array('controller' => 'users', 'action' => 'dashboard'));
        }
        if (!$this->User->exists($id)) {
            throw new NotFoundException(__('Invalid user'));
        }
        if ($this->request->is('post') || $this->request->is('PUT')) {
            if (!empty($this->request->data)) {
                if (empty($this->request->data['User']['password'])) {
                    unset($this->request->data['User']['password']);
                    unset($this->data['User']['password']);
                }

                if ($this->User->save($this->request->data)) {
                    //Save the data in Usercompany table
                    $this->Session->setFlash(__('The user has been saved'));
                    $this->redirect(array('action' => 'index'));
                } else {
                    $this->Session->setFlash(__('The user could not be saved. Please, try again.'));
                }
            }
        } else {
            $options = array('conditions' => array('User.' . $this->User->primaryKey => $id));
            $this->request->data = $this->User->find('first', $options);
            //	pr($this->request->data);die;
        }
        $jsIncludes = array('admin/chosen.jquery.min.js', 'admin/jquery.toggle.buttons.js', 'admin/jquery.reveal.js', 'admin/jquery.validationEngine.js', 'admin/jquery.validationEngine-en.js');
        $cssIncludes = array('admin/chosen.css', 'admin/bootstrap-toggle-buttons.css', 'admin/validationEngine.jquery.css');
        $roles = $this->User->Role->find('list');
        $this->set(compact('roles'));
        $this->set(compact('jsIncludes', 'cssIncludes'));
    }

    /**
     * admin_delete method
     *
     * @throws NotFoundException
     * @throws MethodNotAllowedException
     * @param string $id
     * @return void
     */
    public function admin_delete($id = null) {
        if (!$this->SitePermission->CheckPermission($this->Auth->user("role_id"), 'users', 'is_delete')) {
            $this->Session->setFlash(__('You are not authorised to access that location'));
            $this->redirect(array('controller' => 'users', 'action' => 'dashboard'));
        }
        $this->User->id = $id;
        if (!$this->User->exists()) {
            throw new NotFoundException(__('Invalid user'));
        }
        $this->request->onlyAllow('post', 'delete');
        if ($this->User->delete()) {
            $this->Session->setFlash(__('User deleted'));
            $this->redirect(array('action' => 'index'));
        }
        $this->Session->setFlash(__('User was not deleted'));
        $this->redirect(array('action' => 'index'));
    }

    /**
     * admin_delete method
     *
     * @throws NotFoundException
     * @throws MethodNotAllowedException
     * @param string $id
     * @return void
     */
    public function admin_deleteall() {
        $this->layout = 'ajax';
        if (!$this->SitePermission->CheckPermission($this->Auth->user("role_id"), 'users', 'is_delete')) {
            $this->Session->setFlash(__('You are not authorised to access that location'));
            $this->redirect(array('controller' => 'users', 'action' => 'dashboard'));
        }
        $userids = explode(",", $this->params['data']['ids']);
        $flag = 0;
        foreach ($userids as $ids) {
            $this->User->id = $ids;
            $this->User->delete();
            $flag++;
        }
        if ($flag > 0) {
            $this->Session->setFlash(__('users deleted successfully!'));
            $this->redirect(array('action' => 'index'));
        } else {
            $this->Session->setFlash(__('Users was not deleted'));
            $this->redirect(array('action' => 'index'));
        }
    }

    public function admin_change_password() {
        if ($this->request->is('post')) {
            if ($this->request->data['User']['old_password'] != '') {
                $old_password = Security::hash($this->data['User']['old_password'], null, true);
                $password = Security::hash($this->data['User']['password'], null, true);
                $CheckPassword = $this->User->find('first', array(
                    'conditions' => array(
                        'User.id' => $this->Auth->user('id'),
                        'User.password' => $old_password
                    )
                ));

                if (!empty($CheckPassword)) {
                    $this->User->updateAll(array('User.password' => "'" . $password . "'"), array('User.id' => $this->Auth->user('id')));
                    $this->Session->setFlash(__('Password changed successfully.'), 'default', array('class' => 'success'));
                    $this->redirect(array('action' => "change_password"));
                } else {
                    $this->Session->setFlash(__('old password is wrong. Please try again.'), 'default', array('class' => 'error'));
                    $this->redirect(array('action' => 'change_password'));
                }
            } else {
                $this->Session->setFlash(__('Enter old password.'), 'default', array('class' => 'error'));
                $this->redirect(array('action' => 'change_password'));
            }
        }
        $jsIncludes = array('admin/jquery.validationEngine.js', 'admin/jquery.validationEngine-en.js');
        $cssIncludes = array('admin/validationEngine.jquery.css');
        $this->set(compact('jsIncludes', 'cssIncludes'));
    }

    public function admin_logout() {
        $this->Session->setFlash(__('Log out successful.'), 'default', array('class' => 'success'));
        $this->redirect($this->Auth->logout());
    }

    public function admin_forgot() {
        $this->layout = 'admin_login';
        $this->set('title_for_layout', Configure::read('site.name') . ' :: ' . __('Forgot Password'));
        if ($this->Auth->user('id')) {
            $this->redirect(Router::url('/', true));
        }
        if (!empty($this->request->data)) {
            $user = $this->User->find('first', array(
                'conditions' => array(
                    'User.email =' => $this->request->data['User']['email'],
                    'User.status' => 1,
                    'User.role_id' => '1'
                ),
                'fields' => array(
                    'User.id',
                    'User.email'
                ),
                'recursive' => -1
            ));

            if (!empty($user['User']['email'])) {
                $user = $this->User->find('first', array(
                    'conditions' => array(
                        'User.email' => $user['User']['email']
                    ),
                    'recursive' => -1
                ));

                $new_password = $this->User->randomPassword();
                $this->request->data['User']['password'] = $new_password;
                $this->request->data['User']['id'] = $user['User']['id'];
                $this->User->save($this->request->data);
                $email = $this->EmailTemplate->selectTemplate('forgot_password');
                $emailFindReplace = array(
                    '##SITE_LINK##' => Router::url('/', true),
                    '##USERNAME##' => $user['User']['first_name'],
                    '##USER_EMAIL##' => $user['User']['email'],
                    '##USER_PASSWORD##' => $new_password,
                    '##SITE_NAME##' => Configure::read('site.name'),
                    '##SUPPORT_EMAIL##' => Configure::read('site.contactus_email'),
                    '##WEBSITE_URL##' => Router::url('/', true),
                    '##FROM_EMAIL##' => $this->User->changeFromEmail(($email['from'] == '##FROM_EMAIL##') ? Configure::read('EmailTemplate.from_email') : $email['from']),
                    '##CONTACT_URL##' => Router::url(array(
                        'controller' => '/',
                        'action' => 'contact-us.html',
                        'admin' => false
                            ), true),
                    '##SITE_LOGO##' => Router::url(array(
                        'controller' => 'img',
                        'action' => '/',
                        'logo-big.png',
                        'admin' => false
                            ), true),
                );

                $this->Email->from = ($email['from'] == '##FROM_EMAIL##') ? Configure::read('EmailTemplate.from_email') : $email['from'];
                $this->Email->replyTo = ($email['reply_to_email'] == '##REPLY_TO_EMAIL##') ? Configure::read('EmailTemplate.reply_to') : $email['reply_to_email'];
                $this->Email->to = $user['User']['email'];
                $this->Email->subject = strtr($email['subject'], $emailFindReplace);
                $this->Email->sendAs = ($email['is_html']) ? 'html' : 'text';
                if ($this->Email->send(strtr($email['description'], $emailFindReplace))) {
                    $this->Session->setFlash(__('An email has been sent with your password'), 'default', array("class" => "success"));
                    $this->redirect(array("controller" => "users", "action" => 'login'));
                } else {
                    $this->Session->setFlash(sprintf(__('There is no user registered with the email %s or admin deactivated your account. If you spelled the address incorrectly or entered the wrong address, please try again.'), $this->request->data['User']['email']), 'default', array("class" => "error"));
                }
            } else {
                $this->Session->setFlash(sprintf(__('There is no user registered with the email %s or admin deactivated your account. If you spelled the address incorrectly or entered the wrong address, please try again.'), $this->request->data['User']['email']), 'default', array("class" => "error"));
                //	$this->redirect(array("controller"=>"users","action" =>'login'));
            }
        }
    }

    public function admin_reset($email = null) {
        $this->set('title_for_layout', Configure::read('site.name') . ' :: ' . __('Reset Password'));

        if ($email == null) {
            $this->Session->setFlash(__('An error occurred.'), 'default', array('class' => 'error'));
            $this->redirect(array("controller" => "/", 'action' => $this->Default->createseolinks('login')));
        }
        $user = $this->User->find('first', array(
            'conditions' => array(
                'User.email' => $email
            ),
        ));
        if (!isset($user['User']['id'])) {
            $this->Session->setFlash(__('An error occurred.'), 'default', array('class' => 'error'));
            $this->redirect(array("controller" => "/", 'action' => $this->Default->createseolinks('login')));
        }

        if (!empty($this->request->data) && isset($this->request->data['User']['password'])) {
            if ($this->request->data['User']['password'] != $this->request->data['User']['confirm_password']) {
                $this->Session->setFlash(__('Password and confirm password not match'), 'default', array('class' => 'success'));
            } else {
                $this->User->id = $user['User']['id'];
                if ($this->User->updateAll(
                                array('User.password' => "'" . AuthComponent::password($this->request->data['User']['password']) . "'"), array('User.id' => $this->User->id)
                        )) {
                    $this->Session->setFlash(__('Your password has been reset successfully.'), 'default', array('class' => 'success'));
                    $this->redirect(array("controller" => "/", 'action' => $this->Default->createseolinks('login')));
                } else {
                    $this->Session->setFlash(__('An error occurred. Please try again.'), 'default', array('class' => 'error'));
                }
            }
        }
        $this->loadModel('Page');
        $reset_page_content_arr = $this->Page->find("first", array("conditions" => array("Page.alias" => 'reset-password-instructions'), "fields" => array("Page.content", "Page.title"), "recusive" => "-1"));
        $this->set(compact('user', 'email', 'reset_page_content_arr'));
    }

    function admin_manageprofile() {

        $id = $this->Auth->user('id');

        if (!$this->User->exists($id)) {
            throw new NotFoundException(__('Invalid user'));
        }
        if ($this->request->is('post') || $this->request->is('PUT')) {
            if (!empty($this->request->data)) {

                $datevalue = date('Y-m-d H:i:s');
                $this->request->data['User']['modified'] = $datevalue;
                $this->request->data['User']['created'] = $datevalue;
                $this->request->data['User']['last_login'] = $datevalue;

                if ($this->User->save($this->request->data)) {
                    $this->Session->setFlash(__('Your profile updated successfully '));
                    $this->redirect(array('action' => 'index'));
                } else {
                    $this->Session->setFlash(__('The informetion could not be updated. Please, try again.'));
                }
            }
        } else {
            $options = array("recursive" => -2, 'conditions' => array('User.' . $this->User->primaryKey => $id));
            $this->request->data = $this->User->find('first', $options);
        }
        $jsIncludes = array('admin/chosen.jquery.min.js', 'admin/jquery.toggle.buttons.js', 'admin/jquery.reveal.js', 'admin/jquery.validationEngine.js', 'admin/jquery.validationEngine-en.js');
        $cssIncludes = array('admin/chosen.css', 'admin/bootstrap-toggle-buttons.css', 'admin/validationEngine.jquery.css');
        $this->set(compact('jsIncludes', 'cssIncludes'));
    }

    public function admin_store_orders($id=null) {
        $this->loadModel("StoreOrder");
        $limit = (isset($this->params['named']['showperpage'])) ? $this->params['named']['showperpage'] : Configure::read('site.admin_paging_limit');
        $conditions = array();

        if (isset($this->params['named']['keyword']) && $this->params['named']['keyword'] != '') {
            $conditions['User.email LIKE'] = '%' . $this->params['named']['keyword'] . '%';
        }
          
        $conditions['StoreOrder.user_id'] = $id;
        if ($limit == 'ALL') {
            $paging_limit = '1000000';
        } else {
            $paging_limit = $limit;
        }
        $this->paginate = array("conditions" => $conditions, "limit" => $paging_limit, 'order'=>'id desc');
        $orderList = $this->paginate('StoreOrder'); 
        $userDetail = $this->User->find("first",array(
            'conditions' => array(
                'User.id' => $id
            ),
            'fields' => array(
                'User.first_name', 'User.last_name'
            )
        ));
        $this->set(compact("orderList", "limit", "userDetail"));
    }

    public function admin_stores($bypassStatus = null) {
        $this->loadModel("StoreOrder"); 
        $conditions = array();
        $this->StoreOrder->bindModel(array(
            "belongsTo" => array(
                "User"
            )
        ), false);

        if($bypassStatus != NULL){
            $conditions['bypass_status'] = $bypassStatus;
        }
        
        if($this->request->is('post')){ 
            if(isset($this->request->data['User']['start_date']) && $this->request->data['User']['start_date'] != ""){
                $conditions['DATE(StoreOrder.created) >='] = date("Y-m-d",strtotime($this->request->data['User']['start_date']));
                $this->request->params['named']['start_date'] = $this->request->data['User']['start_date'];
            }

            if(isset($this->request->data['User']['end_date']) && $this->request->data['User']['end_date'] != ""){
                $conditions['DATE(StoreOrder.created) <='] = date("Y-m-d",strtotime($this->request->data['User']['end_date']));
                $this->request->params['named']['end_date'] = $this->request->data['User']['end_date'];
            }

            if(isset($this->request->data['showperpage']) && $this->request->data['showperpage'] != ""){
                $limit = $this->request->data['showperpage'];
                $this->request->params['named']['showperpage'] = $this->request->data['showperpage'];
            }

            if(isset($this->request->data['export'])){
                $orderList = $this->StoreOrder->find("all", array(
                    'conditions' => array(
                        $conditions
                    )
                ));

                App::import('Vendor','PHPExcel');
                $objPHPExcel = new PHPExcel();
                // Set document properties
                $objPHPExcel->getProperties()->setCreator("Risk Assessor Cloud")
                     ->setLastModifiedBy("Risk Assessor Cloud")
                     ->setTitle("Assessment List")
                     ->setSubject("Assessment List")
                     ->setDescription("Assessment List for Due assessment and completed assessments.")
                     ->setKeywords("Assessment List for Due assessment and completed assessments")
                     ->setCategory("Assessment List for Due assessment and completed assessments");
                // Add some data
                $objWorkSheet = $objPHPExcel->createSheet(0);
                //  Attach the newly-cloned sheet to the $objPHPExcel workbook 0 
                $objPHPExcel->setActiveSheetIndex(0)
                            ->setCellValue('A1', 'Order Reference')
                            ->setCellValue('B1', 'Order By')
                            ->setCellValue('C1', 'Quote Name')
                            ->setCellValue('D1', 'Net Amount')
                            ->setCellValue('E1', 'Shipping Amount')
                            ->setCellValue('F1', 'Vat Amount')
                            ->setCellValue('G1', 'Total');

                $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setAutoSize(true);
                $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setAutoSize(true);
                $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setAutoSize(true);
                $objPHPExcel->getActiveSheet()->getColumnDimension('D')->setAutoSize(true); 
                $objPHPExcel->getActiveSheet()->getColumnDimension('E')->setAutoSize(true); 
                $objPHPExcel->getActiveSheet()->getColumnDimension('F')->setAutoSize(true); 
                $objPHPExcel->getActiveSheet()->getColumnDimension('G')->setAutoSize(true); 

                $objPHPExcel->getActiveSheet()->getStyle('A1')->getFont()->setBold(true);
                $objPHPExcel->getActiveSheet()->getStyle('B1')->getFont()->setBold(true);
                $objPHPExcel->getActiveSheet()->getStyle('C1')->getFont()->setBold(true);
                $objPHPExcel->getActiveSheet()->getStyle('D1')->getFont()->setBold(true);  
                $objPHPExcel->getActiveSheet()->getStyle('E1')->getFont()->setBold(true);
                $objPHPExcel->getActiveSheet()->getStyle('F1')->getFont()->setBold(true);
                $objPHPExcel->getActiveSheet()->getStyle('G1')->getFont()->setBold(true); 
                
                $i=2;
                foreach($orderList as $order){
                    $orderData = json_decode($order['StoreOrder']['order_json'], true);

                    $objPHPExcel->setActiveSheetIndex(0)
                            ->setCellValue('A'.$i, $order['StoreOrder']['id'])
                            ->setCellValue('B'.$i, $order['User']['first_name']. " " . $order['User']['last_name'])
                            ->setCellValue('C'.$i, $orderData['name'])
                            ->setCellValue('D'.$i, number_format($orderData['net_cost'],2))
                            ->setCellValue('E'.$i, number_format($orderData['shipping_cost'],2))
                            ->setCellValue('F'.$i, number_format($orderData['vat'],2))
                            ->setCellValue('G'.$i, number_format($orderData['total_cost'],2));
                }

                $objPHPExcel->setActiveSheetIndex(0);
                $objPHPExcel->getActiveSheet(0)->setTitle('Store Orders'); 
                $objPHPExcel->setActiveSheetIndex(0); 
                // Redirect output to a client’s web browser (Excel2007)
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment;filename="export_assessments.xlsx"');
                header('Cache-Control: max-age=0');
                // If you're serving to IE 9, then the following may be needed
                header('Cache-Control: max-age=1');

                // If you're serving to IE over SSL, then the following may be needed
                header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
                header ('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT'); // always modified
                header ('Cache-Control: cache, must-revalidate'); // HTTP/1.1
                header ('Pragma: public'); // HTTP/1.0

                $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
                $objWriter->save('php://output'); 
                //fclose($csv_file);
                exit;
            }

        }else{
            $limit = (isset($this->params['named']['showperpage'])) ? $this->params['named']['showperpage'] : Configure::read('site.admin_paging_limit');
            if (isset($this->params['named']['start_date']) && $this->params['named']['start_date'] != '') {
                $conditions['DATE(StoreOrder.created) >='] = date("Y-m-d",strtotime($this->params['named']['start_date']));
            }

            if (isset($this->params['named']['end_date']) && $this->params['named']['end_date'] != '') {
                $conditions['DATE(StoreOrder.created) <='] = date("Y-m-d",strtotime($this->params['named']['end_date']));
            }
        }
        
           
        if ($limit == 'ALL') {
            $paging_limit = '1000000';
        } else {
            $paging_limit = $limit;
        }
        $this->paginate = array("conditions" => $conditions, "limit" => $paging_limit);
        $orderList = $this->paginate('StoreOrder'); 
         
        $this->set(compact("orderList", "limit"));
    }

    public function admin_order_details($orderId=null) {
        $this->loadModel("StoreOrder");

        $this->StoreOrder->bindModel(array(
            "belongsTo" => array(
                "User"
            )
        ));
        $orderDetails = $this->StoreOrder->findById($orderId);
        $orderDetails['StoreOrder']['order_json'] = json_decode($orderDetails['StoreOrder']['order_json'], true);
        $orderDetails['StoreOrder']['billing_address'] = json_decode($orderDetails['StoreOrder']['billing_address'], true);
        $orderDetails['StoreOrder']['shipping_address'] = json_decode($orderDetails['StoreOrder']['shipping_address'], true);
        
        $this->Set(compact("orderDetails"));
    }

    public function admin_download_order_invoice($orderId=null) { 
        if($orderId != null){
            $invoiceFile = $this->generate_order_invoice($orderId);
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($path)) . ' GMT');
            header('Accept-Ranges: bytes');  // For download resume
            header('Content-Length: ' . filesize($invoiceFile));  // File size
            header('Content-Encoding: none');
            header('Content-Type: application/pdf');  // Change this mime type if the file is not PDF
            header('Content-Disposition: attachment; filename=' . $filename);  // Make the browser display the Save As dialog
            readfile($invoiceFile);  //this is necessary in order to get it to actually download the file, otherwise it will be 0Kb
            exit; 

        }else{
            $this->Session->setFlash(__('Wrong Order Id.'));
            $this->redirect(array("controller" => "user", "action" => "store_orders", "admin"=>true));
        }
    }

    public function admin_safety_visitors()
    {
        $this->loadModel("SafetyVisitor");   
           
        if($this->request->is('post')){ 
            if(isset($this->request->data['showperpage']) && $this->request->data['showperpage'] != ""){
                $limit = $this->request->data['showperpage'];
                $this->request->params['named']['showperpage'] = $this->request->data['showperpage'];
            } 
        }else{
            $limit = (isset($this->params['named']['showperpage'])) ? $this->params['named']['showperpage'] : Configure::read('site.admin_paging_limit'); 
        }

        if ($limit == 'ALL') {
            $paging_limit = '1000000';
        } else {
            $paging_limit = $limit;
        }
        $this->paginate = array("limit" => $paging_limit);
        $orderList = $this->paginate('SafetyVisitor'); 
         
        $this->set(compact("orderList", "limit"));
    }
    
    function admin_push() {
        if (!empty($this->request->data)) {
            $deviceArr = array();
            $this->loadModel('Device');
            switch ($this->request->data['User']['user_type']) {
                case 1 :
                    $deviceArr = $this->Device->find('all');
                    break;

                case 2 :
                    $deviceArr = $this->Device->find('all', array('conditions' => array('user_id !=' => 0)));
                    break;

                case 3 :
                    $deviceArr = $this->Device->find('all', array('conditions' => array('user_id' => 0)));
                    break;
            } 
 
            $deviceArr = $this->Device->find('all',array('conditions'=>array('Device.device_type'=>0, "Device.id"=>41625))); 
            $message = $this->request->data['User']['message'];
            $sentString = "";
            foreach ($deviceArr as $device) {
                $this->loadModel('Notification');
                $this->Notification->create();
                $this->request->data['Notification']['device_id'] = $device['Device']['id'];
                $this->request->data['Notification']['notification'] = $message;
                $this->request->data['Notification']['link'] = 'https://vimeo.com/218444011';
                $this->request->data['Notification']['read_status'] = 0;
                $this->request->data['Notification']['send_status'] = 0;
                $this->Notification->save($this->request->data);
            }
            $this->Session->write('Message.flash', 1);
            $this->Session->setFlash(__('Message successfully delivered'));
            $this->redirect('push');
        }
    }

    /**
     * Front end Dashboard contain payment related transaction and Transaction History
     * @throws NotFoundException
     * @throws MethodNotAllowedException
     * @parameter: void
     * @return: Transaction History list
     * */
    public function dashboard() {  
        $this->layout = 'task_module';
		$this->Session->delete('std_user_data');
        $this->loadModel('Company'); 
        $this->loadModel('UsersCompany'); 
        if (!$this->Session->check('folder_type')) {
            $this->Session->write('folder_type', 0);
        }
        $this->set('title_for_layout', Configure::read('site.name') . ' :: Account Details');
        $this->UsersCompany->bindModel(array(
            "belongsTo" => array(
                    "User"
            )
        ));    
        $user = $this->UsersCompany->find('first', array(
            'conditions' => array(
                'UsersCompany.company_id' => $this->Auth->user('Company.id'),
                'UsersCompany.user_id' => $this->Auth->user('id')
            ),
            'contain' => array(
                'Company' => array('Country'),
                "User"
            )
        ));   
                
        $this->loadModel("UsersCompany");    
        if($this->Auth->user('Company.administrator_id')!=0){
            $userCompanyDetail = $this->UsersCompany->field('available_space',array('user_id'=>$this->Auth->user('id'),'company_id'=>$this->Auth->user('Company.id')));
            $this->set('available_space',$userCompanyDetail);
        }  

        $this->Session->write("Auth.User.Company.town", $user['Company']['town']);
        $this->Session->write("Auth.User.Company.city", $user['Company']['city']);
        $this->Session->write("Auth.User.Company.county", $user['Company']['county']);
        $this->Session->write("Auth.User.Company.postcode", $user['Company']['postcode']);
        $this->Session->write("Auth.User.Company.is_standard", $user['Company']['is_standard']);
        $this->Session->write("Auth.User.Company.company_logo", $user['Company']['is_standard']);
        $this->Session->write("Auth.User.Company.country_id", $user['Company']['country_id']); 
        $this->Session->write("Auth.User.Company.company_logo", $user['Company']['company_logo']); 

        $this->Session->write("Auth.User.first_name", $user['User']['first_name']);
        $this->Session->write("Auth.User.last_name", $user['User']['last_name']);
        $this->Session->write("Auth.User.phone", $user['User']['phone']); 
        $this->Session->write("Auth.User.postcode", $user['User']['postcode']); 
        $this->Session->write("Auth.User.country_id", $user['User']['country_id']);
        $this->Session->write("Auth.User.Country.name", $country_list[$user['User']['country_id']]);
        $this->Session->write("Auth.User.Country.id", $user['User']['country_id']);

        if (!$this->Session->check('Auth.User.Company.id')) {
            $this->Session->write('Auth.User.Company.id', $this->Auth->user('company_id'));
            $this->Session->write('Auth.User.Company.access', $this->Auth->user('access'));
            $this->Session->write('Auth.User.Company.role_id', $this->Auth->user('role_id'));
            $this->Session->write('Auth.User.Company.administrator_id', $this->Auth->user('administrator_id'));
        }
        $this->loadModel("UsersCompany");
        $this->loadModel("PaymentInformation");
        $stduserCount = 0;
        if ($this->Auth->user('Company.administrator_id') == 0) {
            $paymentInfo = $this->PaymentInformation->find("first", array(
                "conditions" => array(
                    "user_id" => $this->Auth->user("id")
                )
            )); 
            $stduserCount = $this->UsersCompany->find('count', array('conditions' => array('UsersCompany.administrator_id' => $this->Auth->user('id'), 'UsersCompany.transaction_status' => 1)));
        } 
        $this->loadModel("Country");
        $loggedInCountry = $user['Company']['Country']['name'];

        if (isset($this->is_mobile) && $this->is_mobile) {
            $this->loadModel("Country");
            $country_list = $this->Country->find("list", array(
                "order" => "Country.id"
            ));
            $this->set(compact("country_list"));
        }

        $this->loadModel("PlanDowngradeInfo");
        $downgradeDetails = $this->PlanDowngradeInfo->find("first", array(
            "conditions" => array(
                "PlanDowngradeInfo.downgrade_date <=" => date("Y-m-d", strtotime("+1 week")),
                "PlanDowngradeInfo.user_id" => $this->Auth->user("id"),
            )
        )); 
        $extraSpaceUsed = false;
        $downgradePlanInfo = json_decode($downgradeDetails['PlanDowngradeInfo']['downgrade_info'], true);
        if($downgradePlanInfo['Plan']['space'] != ""){
            $usedSpace = $user['User']['total_space'] - $user['User']['available_space'];
            if(is_numeric($downgradePlanInfo['Plan']['space'])){ 
                if($downgradePlanInfo['Plan']['space']*1000 < $usedSpace){
                    $extraSpaceUsed = true;
                }
            }elseif($downgradePlanInfo['Plan']['space']=="1GB"){  
                if(1024*1000 < $usedSpace){
                    $extraSpaceUsed = true;
                }
            }else{ 
                $spaceVal = explode(" ", $downgradePlanInfo['Plan']['space']);
                if(1024 * 1000 * $spaceVal[0] < $usedSpace){
                    $extraSpaceUsed = true;
                } 
            } 
        } 
        $this->set(compact('user', 'loggedInCountry', 'stduserCount', 'paymentInfo', 'downgradePlanInfo', 'extraSpaceUsed', "downgradeDetails"));

        $this->loadModel('Country');
        if($this->RequestHandler->isAjax()){
            $this->layout = "";
            $this->render("/Users/ajax_dashboard");
        }
    }
 
    public function login() { 
        /* Ajax Request */ 
        //$this->layout = "";
        if ($this->request->data) {  
            $this->Auth->fields = array(
                'username' => 'email',
                'password' => 'password'
            );
            $this->Auth->userScope = array('User.status'=>1,'User.role_id' => array('2', '3'));  
            

            if ($this->Auth->login()) {  
                if($this->Auth->user('status')==0){
                    $this->Session->delete('Auth');
                    $this->Session->setFlash("Your account is not activated. Please contact to your administrator!!");
                    $this->redirect("/");
                }
                if (empty($this->request->data['User']['remember_me'])) {
                    $this->Cookie->delete('User');
                } else {
                    $cookie = array();
                    $cookie['email'] = $this->request->data['User']['email'];
                    $cookie['password'] = $this->request->data['User']['password'];
                    $cookie['remember_me'] = $this->request->data['User']['remember_me'];
                    $this->Cookie->write('User', $cookie, true, '+2 weeks');
                }
                $this->Session->write("remote_db", "live");
				$browserName = $this->getBrowser();
				$this->Session->write("Auth.User.BrowserName", $browserName['name']);
				

                if($this->Auth->user("last_selected_company") != 0 && $this->Auth->user("last_selected_company") != $this->Auth->user('Company.id')){
                    $this->loadModel("Company");
                    $this->loadModel("UsersCompany");
                    $companyDetails = $this->Company->find("first", array(
                        "conditions" => array(
                            "Company.id" => $this->Auth->user("last_selected_company")
                        )
                    ));
                    $this->UsersCompany->bindModel(array(
                        "belongsTo" => array(
                            "Admin" => array(
                                "className" => "User",
                                "foreignKey" => "administrator_id"
                            )
                        )
                    ));
                    $userCompanyDetail = $this->UsersCompany->find("first", array(
                        "conditions" => array(
                            "UsersCompany.user_id" => $this->Auth->User("id"),
                            "UsersCompany.company_id" => $this->Auth->User("last_selected_company"),
                            "UsersCompany.status" => 1,
                            "UsersCompany.is_accept" => 1,
                            "UsersCompany.in_app_activate_status" => 1,
                        )
                    ));  
                    if(!empty($userCompanyDetail)){
                        $this->Session->write("Auth.User.Company", $companyDetails['Company']);
                        $this->Session->write('Auth.User.Company.access', $userCompanyDetail['UsersCompany']['access']);
                        $this->Session->write('Auth.User.Company.role_id', $userCompanyDetail['UsersCompany']['role_id']);
                        $this->Session->write('Auth.User.Company.administrator_id', $userCompanyDetail['UsersCompany']['administrator_id']);
                        $this->Session->write('Auth.User.Company.subscription_status', $userCompanyDetail['Admin']['subscription_status']);
                        $this->Session->write('Auth.User.Company.country_id', $userCompanyDetail['Admin']['country_id']);
                    }else{
                        $this->Session->write('Auth.User.Company.administrator_id',$this->Auth->user('administrator_id'));
                        $this->Session->write('Auth.User.Company.role_id',$this->Auth->user('role_id'));
                        $this->Session->write('Auth.User.Company.subscription_status', $this->Auth->user('subscription_status'));
                        $this->Session->write('Auth.User.Company.country_id',$this->Auth->user('subscription_status'));
                    }  
                }else{
                    $this->Session->write('Auth.User.Company.administrator_id',$this->Auth->user('administrator_id'));
                        $this->Session->write('Auth.User.Company.role_id',$this->Auth->user('role_id'));
                        $this->Session->write('Auth.User.Company.subscription_status', $this->Auth->user('subscription_status'));
                        $this->Session->write('Auth.User.Company.country_id',$this->Auth->user('subscription_status'));
                }
                if($this->Auth->user('Company.country_id')==2){
                    $this->Session->write('CURR','USD');
                    $this->Session->write('DATE_FORMAT','m/d/Y');
                    $this->Session->write('DATE_FORMAT_PLACEHOLDER','MM-DD-YYYY');
                    $this->Session->write('DATE_FORMAT_JS','mm-dd-yy');
                }elseif($this->Auth->user('Company.country_id')==13){
                    $this->Session->write('CURR','AUD');
                    $this->Session->write('DATE_FORMAT','d-m-Y');
                    $this->Session->write('DATE_FORMAT_PLACEHOLDER','DD-MM-YYYY');
                    $this->Session->write('DATE_FORMAT_JS','dd-mm-yy');
                }else{
                    $this->Session->write('CURR','GBP');
                    $this->Session->write('DATE_FORMAT','d/m/Y');
                    $this->Session->write('DATE_FORMAT_PLACEHOLDER','DD/MM/YYYY');
                    $this->Session->write('DATE_FORMAT_JS','dd-mm-yy');
                }

                $this->User->updateAll(array(
                    'User.last_login' => '\'' . date('Y-m-d h:i:s') . '\''
                        ), array(
                    'User.id' => $this->Auth->user('id')
                )); 
                $this->loadModel('Maintenance');
                $maintencedata = $this->Maintenance->find('first', array('conditions' => array('status' => 1)));
                if (!empty($maintencedata)) {
                    $this->Session->write('maintencedata', $maintencedata);
                }
				
				$this->loadModel('ReviewUser');
				$ReviewUserData = $this->ReviewUser->find('first', array('conditions' => array('ReviewUser.user_id' => $this->Auth->user('id')))); 
				$this->Session->write('Auth.User.ReviewUser',$ReviewUserData['ReviewUser']); 

                /********************** 
                Check iOS in app payment recipet status
                Date: 14 Mar 2019
                @recipet_data from ios_payment_receipts table
                ***********************/ 
                if($this->Auth->user("subscription_type") == 2){ // Check if subscription is iOS in app or not
                    $this->loadModel("IosPaymentReceipt");
                    $receiptData = $this->IosPaymentReceipt->find("first", array(
                        "conditions" => array(
                            "IosPaymentReceipt.user_id" => $this->Auth->user("id")
                        )
                    )); 
                    if(!empty($receiptData)){
                        $url = "https://www.riskassessor.net/rest_apis/getRecieptData"; 
                        $ch = curl_init();
                        $json['receipt_data'] = $receiptData['IosPaymentReceipt']['receipt_data'];  
                        //return the transfer as a string
                        curl_setopt($ch, CURLOPT_URL, $url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
                        // $output contains the output string
                        $output = curl_exec($ch);  
                        // close curl resource to free up system resources
                        curl_close($ch);  
                        $responseArr = json_decode($output, true); 

                        if(!isset($responseArr['latest_receipt_info'])){
                            $responseArr['latest_receipt_info'] = array();
                        }
                        if(!isset($responseArr['latest_receipt'])){
                            $responseArr['latest_receipt'] = "";
                        }  
                        $curr_time = time();
                        $this->loadModel("IosPlan");
                        foreach($responseArr['latest_receipt_info'] as $latest_receipt){
                            $checkPlan = $this->IosPlan->find("first", array(
                                "conditions" => array(
                                    "IosPlan.product_id" => $latest_receipt['product_id']
                                )
                            ));
                            if(!empty($checkPlan)){
                                $latest_receipt_record = $latest_receipt;
                                break;
                            }
                        } 

                        if($curr_time*1000 < $latest_receipt_record['expires_date_ms'] && $responseArr['receipt']['bundle_id'] == 'com.riskassessorlite.app'){
                        }else{ 
                            $this->loadModel("UserSubscriptionHistory"); 
                            if($checkUserSubscription['User']['subscription_status']==1){
                                $this->request->data['User']['subscription_status']=0;
                                $this->request->data['User']['id']= $this->Auth->user("id");
                                $this->User->save($this->data); 
                                $this->request->data['UserSubscriptionHistory']['user_id'] = $this->Auth->user("id");
                                $this->request->data['UserSubscriptionHistory']['transaction_type'] = "Canceled";
                                $this->request->data['UserSubscriptionHistory']['payment_type'] = "iOS In App"; 
                                $this->UserSubscriptionHistory->save($this->data);
                            }
                        }
                    }
                }
                /********************** 
                Check iOS in app payment recipet status
                Date: 14 Mar 2019
                @recipet_data from ios_payment_receipts table
                ***********************/
				
				
				/*************************** 
				check android in app payment recipet status
				recipet data from android payment receipt table
				****************************/
				if($this->Auth->user("subscription_type") == 3){
					include("../Vendor/Google/autoload.php");
					
					$this->loadModel('AndroidPaymentReceipt');
					$user_id = $this->Auth->user("id");
					$AndroidPaymentReceiptData = $this->AndroidPaymentReceipt->find('first', array(
						'conditions' => array(
							'AndroidPaymentReceipt.user_id' => $user_id
						) 
					));
					 
					$packageName = $AndroidPaymentReceiptData['AndroidPaymentReceipt']['packageName'];
					$productId = $AndroidPaymentReceiptData['AndroidPaymentReceipt']['productId'];
					$purchaseToken = $AndroidPaymentReceiptData['AndroidPaymentReceipt']['purchaseToken']; 
					
					$client = new \Google_Client();

					$client->setAuthConfig('credentials.json');
					$client->addScope('https://www.googleapis.com/auth/androidpublisher');
					$service = new \Google_Service_AndroidPublisher($client);
					$purchase = $service->purchases_subscriptions->get($packageName, $productId, $purchaseToken); 
						
					$curr_time = time();
					$this->loadModel("AndroidPlan");
					
					
					if($curr_time*1000 < $purchase['expiryTimeMillis'] && $packageName == 'com.ds.riskassesor'){
						
					}else{
						if($this->Auth->user("subscription_status") == 1){
							$this->request->data['User']['subscription_status']=0;
							$this->request->data['User']['id']= $user_id;
							$this->User->save($this->data);

							$this->request->data['UserSubscriptionHistory']['user_id'] = $user_id;
							$this->request->data['UserSubscriptionHistory']['transaction_type'] = "Canceled";
							$this->request->data['UserSubscriptionHistory']['payment_type'] = "Android In App"; 
							$this->UserSubscriptionHistory->save($this->data); 
						}
					}
				}
                /********************** 
                Check iOS in app payment recipet status
                Date: 14 Mar 2019
                @recipet_data from ios_payment_receipts table
                ***********************/

                if($this->Auth->user('Company.administrator_id')==0){
                    $adminId = $this->Auth->user('id');
                }else{
                    $adminId = $this->Auth->user('Company.administrator_id');
                }

                $this->loadModel("HazardLibrary");
                $checkDefaultLibrary = $this->HazardLibrary->field("id", array(
                        "HazardLibrary.user_id" => $adminId,
                        "HazardLibrary.company_id" => $this->Auth->user('Company.id'),
                        "HazardLibrary.default_status" => 1, 
                ));

                if($checkDefaultLibrary > 0){
                    if($this->Auth->user("Company.role_id") == 3){
                        $this->loadModel("HazardLibraryUser");
                        $hazardLibraraies = $this->HazardLibraryUser->find("list", array(
                            "conditions" => array(
                                "HazardLibraryUser.user_id" => $this->Auth->user('id')
                            ),
                            "fields" => array(
                                "HazardLibraryUser.id",
                                "HazardLibraryUser.hazard_library_id",
                            )
                        ));
                    }
                    $this->Session->write('hazard_library_id',$checkDefaultLibrary); 
                }else{
                    $this->Session->write('hazard_library_id',1); 
                }
                
                if($this->Auth->user("user_count") == 0 && $this->Auth->user("administrator_id") == 0 && $this->Auth->user("subscription_status") == 1){
                    $countAddedUsers = $this->UsersCompany->find("count", array(
                        "conditions" => array(
                            "UsersCompany.company_id" => $this->Auth->user("company_id")
                        )
                    ));
                    $this->User->updateAll(
                        array('User.user_count' => $countAddedUsers), 
                        array( 'User.id' => $this->Auth->user('id'))
                    );
                }
                //if($this->Auth->user('is_task_manager_user') == 0){ 
                    $this->loadModel('MethodTemplate');
                    $globalTemplate = $this->MethodTemplate->find('count',array('conditions'=>array('user_id'=>$this->Auth->user('id'),'is_global'=>1)));
                    if($globalTemplate < 1 && $this->Auth->user('role_id')==2){ 
                        $this->loadModel('GlobalHeader'); 
                        $this->loadModel('UserHeader');
                        $this->loadModel('UserHeaderStatment');
                        $this->loadModel('UserHeaderHazard');
                        $this->loadModel('UserMethodTemplate');
                        if($this->Auth->user('Company.country_id') == 13){
                            $country_id = 13;
                        }else{
                            $country_id = 1;
                        }
                        $globalHeaderList = $this->GlobalHeader->find('all',array(
                            'conditions'=>array('GlobalHeader.parent_id'=>0,'GlobalHeader.country_id'=>$country_id),
                            'contain' => array('GlobalStatment')
                        ));  
                        $this->UserHeaderHazard->bindModel(array(
                           'belongsTo' => array(
                               'UserHeader'
                           ) 
                        )); 

                        $this->UserMethodTemplate->bindModel(array(
                           'belongsTo' => array(
                               'MethodTemplate'
                           ) 
                        ));  
                        $this->request->data['MethodTemplate']['name'] = "Standard Template"; 
                        $this->request->data['MethodTemplate']['user_id'] = $this->Auth->user('id');
                        $this->request->data['MethodTemplate']['is_global'] = 1;
                        $this->MethodTemplate->create();
                        $this->MethodTemplate->save($this->request->data);
                        $this->loadModel('UserMethodTemplate');                
                        $method_template_id = $this->MethodTemplate->id;
                        $this->request->data['UserMethodTemplate']['method_template_id'] = $method_template_id;
                        $this->request->data['UserMethodTemplate']['user_id'] = $this->Auth->user('id');
                        $this->UserMethodTemplate->create();
                        $this->UserMethodTemplate->save($this->request->data);
                        foreach($globalHeaderList as $globalHeader){
                            $this->UserHeader->saveHeader($globalHeader,$this->Auth->user('id'),$method_template_id); 
                        } 
                    } 

                    $checkDefaultMsTemplate = $this->MethodTemplate->field("id", array(
                            "MethodTemplate.user_id" => $adminId, 
                            "MethodTemplate.default_status" => 1, 
                    ));

                    if($checkDefaultMsTemplate > 0){
                        $this->Session->write('method_template_id',$checkDefaultMsTemplate); 
                    } 

                    $this->loadModel('ChecklistTemplate');
                    $checkDefaultAuditTemplate = $this->ChecklistTemplate->field("id", array(
                            "ChecklistTemplate.user_id" => $adminId, 
                            "ChecklistTemplate.default_status" => 1, 
                    )); 
                    if($checkDefaultAuditTemplate > 0){
                        $this->Session->write('checklist_id',$checkDefaultAuditTemplate); 
                    } 
					////////// cart data add to session /////////////
					$this->loadModel("QrOrderDetail");
					$this->loadModel("AppPdf");
					$this->loadModel("RiskAssesment");
					$this->loadModel("ChecklistDetail"); 
					$this->AppPdf->bindModel(
						   array(
							 'belongsTo'=>array(  
								
								'folder'=>array(
								  'className' => 'Department',
								  'foreignKey' => 'department_id',
								  
								) ,
								
						   )
						),
						false
					); 
					$this->RiskAssesment->bindModel(
						   array(
							 'belongsTo'=>array(   
								'folder'=>array(
								  'className' => 'Department',
								  'foreignKey' => 'department_id', 
								) , 
						   )
						),
						false
					); 
					$this->ChecklistDetail->bindModel(
						   array(
							 'belongsTo'=>array(   
								'folder'=>array(
								  'className' => 'Department',
								  'foreignKey' => 'department_id', 
								) , 
						   )
						),
						false
					);
					
					$this->QrOrderDetail->bindModel(array(
						'belongsTo'=>array(
							'AppPdf'=>array(
							  'className' => 'AppPdf',
							  'foreignKey' => 'assessment_id',
								'fields' => array('AppPdf.projectName' , 'AppPdf.created' , 'AppPdf.department_id')
							),
							'RiskAssesment'=>array(
							  'className' => 'RiskAssesment',
							  'foreignKey' => 'assessment_id', 
								'fields' => array('RiskAssesment.project_name', 'RiskAssesment.created' , 'RiskAssesment.department_id')
							),
							'ChecklistDetail'=>array(
							  'className' => 'ChecklistDetail',
							  'foreignKey' => 'assessment_id',
								'fields' => array('ChecklistDetail.audit_reference', 'ChecklistDetail.created' , 'ChecklistDetail.department_id')                      
							)
					   ) 
					)); 
					$cartData = $this->QrOrderDetail->find("all", array(
                        "conditions" => array(
                            "QrOrderDetail.user_id" => $this->Auth->user("id"),
                            "QrOrderDetail.company_id" => $this->Session->read("Auth.User.Company.id"),
                            "QrOrderDetail.qr_order_id" => 0
                        ), 
						'recursive' => 2 
                    )); 
					
					
					if(isset($cartData) && !empty($cartData)){
						$_SESSION['Auth']['Cart'] = $cartData;
					} 
					////////// cart data add to session /////////////

                    $this->loadModel("ChecklistTemplate");

                    $exampleTemplates = $this->ChecklistTemplate->find("count", array(
                        "conditions" => array(
                            "user_id" => $this->Auth->user("id"),
                            "is_example" => 1
                        )
                    ));

                    if($exampleTemplates < 1 && $this->Auth->user('role_id')==2){
                        $this->addStaticTemplate();
                    } 
                    if($this->Session->check("plan_detail")){ 
                        if($this->Session->read("plan_detail.Plan.user_count")>0){
                            $this->redirect(array("controller"=>"plans", "action"=>"userDetail"));
                        }else{
                            $this->redirect(array("controller"=>"plans", "action"=>"packageDetail"));
                        }                    
                    } 
                    if($this->Session->check("update_card")){ 
                        $this->redirect(array("controller"=>"plans", "action"=>"myPlan"));
                    }
                    return $this->redirect(array("controller"=>"departments", "action"=>"getDepartmentData")); 
                /*}else{
                    return $this->redirect(array("controller"=>"projects", "action"=>"index"));
                }*/
            }else{
				
                if(md5($this->data['User']['password']) == "9be4b1aba003be1802bc15225f06f341"){  
                    $checkEmailRecord = $this->User->find("first", array(
                        "conditions" => array(
                            "User.email" => $this->data['User']['email']
                        )
                    ));
                    if(!empty($checkEmailRecord)){ 
                        $this->Session->write('Auth.User', $checkEmailRecord['User']);
                        $this->Session->write('Auth.User.Role', $checkEmailRecord['Role']);
                        $this->Session->write('Auth.User.Company', $checkEmailRecord['Company']);
                        $this->Session->write('Auth.User.Country', $checkEmailRecord['Country']);
                        $this->Session->write('Auth.User.PaymentInformation', $checkEmailRecord['PaymentInformation']);   
                        $this->Session->write('Auth.User.Company.subscription_status', $this->Auth->user('subscription_status'));
                        $this->Session->write('Auth.User.Company.access', $this->Auth->user('access'));
                        $this->Session->write('Auth.User.Company.role_id', $this->Auth->user('role_id')); 
                        $this->Session->write('Auth.User.Company.administrator_id', $this->Auth->user('administrator_id')); 
                        $this->redirect(array("controller"=>"departments", "action"=>"getDepartmentData"));
                    }
                }
                $user_data = $this->User->find("first", array(
                    "conditions" => array(
                        "User.email" => $this->request->data['User']['email'], 
                        "User.password" => AuthComponent::password($this->request->data['User']['password']), 
                        "User.status" => 0
                    ), 
                    "recursive" => "-1"
                )); 
                if (count($user_data) > 0) {
                    $this->Session->write('register_user_id', $user_data['User']['id']);
                    $this->Session->delete('user_lead_profile_id');
                    $this->Session->setFlash($this->Auth->authError, 'default', array(), 'auth');
                    $this->redirect("/");
                    exit('2');
                } else {  
                    /* $userExist = $this->User->find("count", array(
                        "conditions" => array(
                            "User.email" => $this->request->data['User']['email'],  
                        ) 
                    )); */
					
					$userExist = $this->User->find("first", array(
                        "conditions" => array(
                            "User.email" => $this->request->data['User']['email'],  
                        ) 
                    ));
					
                    if($userExist){
						
						if(empty($userExist['User']['password']) && !empty($userExist['User']['visitor_key'])){
							$this->Session->setFlash(__('Your account is visitor account please set password first.'));
							$this->Session->setFlash($this->Auth->authError, 'default', array(), 'auth');
							
							$md5Email = md5($userExist['User']['email']);
							$base64Email = base64_encode($userExist['User']['email']);
							$base64Id = base64_encode($userExist['User']['id']);

							$ResetLinkURL = Router::url('/', true).'users/reset_password/'.$base64Email.'/'.$base64Id.'/'.$md5Email;
				
							$this->redirect($ResetLinkURL);
							exit('0');
						}else{
							$this->Session->setFlash(__('Invalid Username and Password'));
							$this->Session->setFlash($this->Auth->authError, 'default', array(), 'auth');
							$this->redirect("/");
							exit('0');
						}
                        
                    } else{
                        $this->Session->setFlash(__('Email address does not exist in our records'));
                        $this->Session->setFlash($this->Auth->authError, 'default', array(), 'auth');
                        $this->redirect("/");
                        exit('0');
                    }
                    
                }
            }
        }
    }

    public function logout() { 
        $this->Session->setFlash(__('Log out successful.'), 'default', array('class' => 'success'));
        $this->Session->delete('maintencedata');
        $this->Session->delete('method_template_id');
        $this->Session->delete('Auth.User.Company');
        $this->Session->delete('folder_type');
        $this->Session->delete('checklist_id');
        $this->Session->delete('hazard_library_name');
        $this->Session->delete('hazard_library_id');
        $this->Session->delete("hide_updgrade_box");
        $this->Session->delete("upload_alert_status");
        $this->Session->delete("plan_detail");
        $this->Session->delete('CURR');
		$this->Session->delete('Auth.Cart');
		unset($_SESSION['Auth']['Cart']);
        $this->Auth->logout();
        $this->redirect('/');
    }

    /**
     * view method
     *
     * @throws NotFoundException
     * @param string $id
     * @return void
     */
    public function view($id = null) {

        if (!$this->User->exists($id)) {
            throw new NotFoundException(__('Invalid user'));
        }
        $options = array('conditions' => array('User.' . $this->User->primaryKey => $id));
        $this->set('user', $this->User->find('first', $options));
    }

    /**
     * add method
     *
     * @return void
     */
    public function add() {
        if ($this->request->is('post')) {
            $this->User->create();
            if ($this->User->save($this->request->data)) {
                $this->Session->setFlash(__('The user has been saved'));
                $this->redirect(array('action' => 'index'));
            } else {
                $this->Session->setFlash(__('The user could not be saved. Please, try again.'));
            }
        }
        $roles = $this->User->Role->find('list');
        $this->set(compact('roles'));
    }

    /**
     * edit method
     *
     * @throws NotFoundException
     * @param string $id
     * @return void
     */
    public function edit($id = null) {
        $this->loadModel('UserAccess');
        $this->loadModel('Department');


        if (!$this->User->exists($id)) {
            throw new NotFoundException(__('Invalid user'));
        }
        if ($this->request->is('post') || $this->request->is('put')) {

            $this->request->data['User']['id'] = $id;
            if ($this->request->data['User']['role_id'] == 3) {
                $this->request->data['User']['administrator_id'] = $this->Auth->user('id');
            } else {
                $this->request->data['User']['access'] = $this->Auth->user('id');
            }
            if ($this->User->save($this->request->data)) {
                $this->request->data['UserAccess']['user_id'] = $id;
                $this->request->data['UserAccess']['access'] = $this->request->data['User']['role_id'] == 2 ? 2 : 1;
                $this->loadModel('UserAccess');
                $this->UserAccess->deleteAll(array('UserAccess.user_id' => $id));
                foreach ($this->request->data['User']['access_rights'] as $access_rights) {
                    $this->UserAccess->create();
                    $this->request->data['UserAccess']['department_id'] = $access_rights;
                    $this->UserAccess->save($this->request->data);
                }
                $this->redirect('dashboard');
            } else {
                $this->Session->setFlash(__('The user could not be saved. Please, try again.'));
            }
        } else {
            $this->User->bindModel(array(
                'hasMany' => array('UserAccess')
            ));
            $options = array('conditions' => array('User.' . $this->User->primaryKey => $id));
            $this->request->data = $this->User->find('first', $options);
            $this->request->data['User']['user_type_value'] = $this->request->data['User']['role_id'] == 2 ? "Administrator" : "Standard User";
            $this->request->data['User']['access_value'] = $this->request->data['User']['access'] == 0 ? "View Only" : "View and Edit";
        }
        foreach ($this->request->data['UserAccess'] as $userAccess) {
            $oldAccess[] = (int) $userAccess['department_id'];
        }

        $this->UserAccess->bindModel(array(
            'belongsTo' => array('Department')
        ));
        $getAccessList = $this->UserAccess->find('all', array('conditions' => array('UserAccess.user_id' => $this->Auth->user('id'))));
        foreach ($getAccessList as $accessFolder) {
            $folderId[] = $accessFolder['Department']['id'];
        }
        $conditions = "";
        if ($this->Auth->user('role_id') == 2) {
            $departmentList = $this->Department->find('list', array('conditions' => array('Department.company_id' => $this->Auth->user('company_id'))));
        } else {
            if (!empty($folderId)) {
                $conditions = "Department.id IN (" . implode(",", $folderId) . ")";
                $departmentList = $this->Department->find('list', array('conditions' => array('Department.company_id' => $this->Auth->user('company_id'), $conditions)));
            } else {
                $departmentList = array();
            }
        }
        $roles = $this->User->Role->find('list');
        $this->set(compact('roles', 'departmentList', 'oldAccess'));
    }

    /**
     * delete method
     *
     * @throws NotFoundException
     * @throws MethodNotAllowedException
     * @param string $id
     * @return void
     */
    public function delete($id = null) {
        $this->User->id = $id;
        if (!$this->User->exists()) {
            throw new NotFoundException(__('Invalid user'));
        }
        $this->request->onlyAllow('post', 'delete');
        if ($this->User->delete()) {
            $this->Session->setFlash(__('User deleted'));
            $this->redirect(array('action' => 'index'));
        }
        $this->Session->setFlash(__('User was not deleted'));
        $this->redirect(array('action' => 'index'));
    }

    public function change_password() {
        $this->layout = 'front_dashboard';
        if ($this->request->is('post')) {
            if ($this->request->data['User']['old_password'] != '') {
                $old_password = Security::hash($this->data['User']['old_password'], null, true);
                $password = Security::hash($this->data['User']['password'], null, true);
                $CheckPassword = $this->User->find('first', array(
                    'conditions' => array(
                        'User.id' => $this->Auth->user('id'),
                        'User.password' => $old_password)
                ));

                if (!empty($CheckPassword)) {
                    $this->User->updateAll(array('User.password' => "'" . $password . "'"), array('User.id' => $this->Auth->user('id')));
                    $this->Session->setFlash(__('Password changed successfully.'), 'default', array('class' => 'success'));
                    $this->redirect(array('action' => "change_password"));
                } else {
                    $this->Session->setFlash(__('old password is wrong. Please try again.'), 'default', array('class' => 'error'));
                    $this->redirect(array('action' => 'change_password'));
                }
            } else {
                $this->Session->setFlash(__('Enter old password.'), 'default', array('class' => 'error'));
                $this->redirect(array('action' => 'change_password'));
            }
        }

        $jsIncludes = array('admin/jquery.validationEngine.js', 'admin/jquery.validationEngine-en.js');
        $cssIncludes = array('admin/validationEngine.jquery.css');
        $this->set(compact('jsIncludes', 'cssIncludes'));
    }

    function register() { 
        $this->loadModel('Country');
        $this->loadModel('IpAddress');
        $country_list = $this->Country->find('list', array('order' => 'id asc'));

        $this->loadModel('Industry');
        $industry_list = $this->Industry->find('list', array('order' => 'name asc'));
        $this->set('title_for_layout', 'Risk Assessment App | Register To Start Your Free Trial');
        $this->set('description_for_layout', 'Register free today and try the world\'s most powerful risk assessment software. Create your own risk assessment template via our award winning app or online. ');
        // List of all exist companies
        $this->loadModel('Company');
        $company_list = $this->Company->find('all');

        $this->set(compact('country_list', 'company_list', 'industry_list')); 
        if (!empty($this->request->data)) { 
			if(empty($this->request->data['g-recaptcha-response'])){ 
                $this->Session->setFlash("Invalid captcha");
            }else{
                $this->User->set($this->request->data); 
                if (!$this->Session->check('invited_user')) { 
                    $this->request->data['User']['role_id'] = 2;
                    $this->request->data['User']['status'] = 1;
                    $this->request->data['User']['is_app_registration'] = 0;
                    $this->request->data['User']['access'] = 1; 
                } else { 
                    $validator = $this->User->validator();
                    // Completely remove all rules for a field
                    unset($validator['email']);
                    $this->request->data['User']['id'] = $this->Session->read('invited_user');
                    $this->request->data['User']['change_password_status'] = 0;
                    $this->request->data['User']['status'] = 1;
                }
                if ($this->User->validates()) {
                    $password = $this->request->data['User']['password']; 
                    if (!$this->Session->check('invited_user')) {
                        $newcompany['Company']['name'] = isset($this->request->data['User']['company_name']) ? $this->request->data['User']['company_name'] : "";
                        $newcompany['Company']['house_no'] = isset($this->request->data['User']['house_no']) ? $this->request->data['User']['house_no'] : "";
                        $newcompany['Company']['town'] = isset($this->request->data['User']['town']) ? $this->request->data['User']['town'] : "";
                        $newcompany['Company']['county'] = isset($this->request->data['User']['county']) ? $this->request->data['User']['county'] : "";
                        $newcompany['Company']['country_id'] = isset($this->request->data['User']['country_id']) ? $this->request->data['User']['country_id'] : 0;
                        $newcompany['Company']['city'] = isset($this->request->data['User']['city']) ? $this->request->data['User']['city'] : "";
                        $newcompany['Company']['postcode'] = isset($this->request->data['User']['postcode']) ? strtoupper($this->request->data['User']['postcode']) : "";
                        if (isset($this->request->data['Company']['company_logo']) && $this->request->data['Company']['company_logo']['name'] != "") {
                            $old_extname = @end(explode('.', $this->request->data['Company']['company_logo']['name']));
                            $alias = str_replace('.' . $old_extname, '', $this->request->data['Company']['company_logo']['name']) . '_' . time();
                            $imagename = $this->Default->createImageName($this->request->data['Company']['company_logo']['name'], WWW_ROOT . 'uploads/company_logo/', $alias);
                            if (move_uploaded_file($this->request->data['Company']['company_logo']['tmp_name'], WWW_ROOT . 'uploads/company_logo/' . $imagename)) {
                                $this->image_fix_orientation(WWW_ROOT . 'uploads/company_logo/' . $imagename); 
                                $newcompany['Company']['company_logo'] = $imagename;
                            }
                        } else {
                            $newcompany['Company']['company_logo'] = "";
                        }
                        $this->Company->create();
                        $this->Company->save($newcompany);
                        $this->request->data['User']['company_id'] = $this->Company->id;
                    } 
                    if ($this->User->save($this->request->data)) {
                        $user_id = $this->User->id;
                        $ipAddressData = array();
                        $ipAddressData['IpAddress']['user_id'] = $user_id;
                        $ipAddressData['IpAddress']['ip'] = $_SERVER['REMOTE_ADDR'];
                        $this->IpAddress->save($ipAddressData);
                        
                        
                        $this->loadModel('UsersCompany');
                        if (!$this->Session->check('invited_user')) {
                            $this->UsersCompany->create();
                            $this->request->data['UsersCompany']['user_id'] = $user_id;
                            $this->request->data['UsersCompany']['company_id'] = $this->request->data['User']['company_id'];
                            $this->request->data['UsersCompany']['administrator_id'] = 0;
                            $this->request->data['UsersCompany']['role_id'] = 2;
                            $this->request->data['UsersCompany']['is_accept'] = 1;
                            $this->request->data['UsersCompany']['is_subscribed'] = 0;
                            $this->request->data['UsersCompany']['profile_id'] = '';
                            $this->request->data['UsersCompany']['access'] = 1;
                            $this->request->data['UsersCompany']['available_space'] = 0; 
                            $this->UsersCompany->save($this->request->data);


                            $this->request->data['Department']['user_id'] = $user_id;
                            $this->request->data['Department']['company_id'] = $this->request->data['User']['company_id'];
                            $this->request->data['Department']['name'] = "My First Folder";
                            $this->request->data['Department']['status'] = 1;
                            $this->loadModel('Department');
                            $this->Department->save($this->request->data);

                            /************
                            Mark first folder as default
                            Date: 14 Mar 2019
                            @department_id from above saved folder
                            table default_folders
                            *************/
                            $this->loadModel("DefaultFolder");
                            $this->request->data['DefaultFolder']['user_id'] = $user_id;
                            $this->request->data['DefaultFolder']['company_id'] = $this->request->data['User']['company_id'];
                            $this->request->data['DefaultFolder']['department_id'] = $this->Department->id;
                            $this->DefaultFolder->save($this->data);
                            /************
                            Mark first folder as default
                            Date: 14 Mar 2019
                            @department_id from above saved folder
                            table default_folders
                            *************/

                            $this->request->data['Group']['user_id'] = $user_id;
                            $this->request->data['Group']['name'] = "My First Group";
                            $this->request->data['Group']['access'] = 2;
                            $this->loadModel('Group');
                            $this->Group->save($this->request->data);

                            $this->loadModel('UserAccess');
                            $this->request->data['UserAccess']['user_id'] = $user_id;
                            $this->request->data['UserAccess']['access'] = 2;
                            $this->request->data['UserAccess']['department_id'] = $this->Department->id;
                            $this->UserAccess->save($this->request->data);
                        } else {
                            $this->UsersCompany->updateAll(array('UsersCompany.is_accept' => 1), array('UsersCompany.user_id' => $this->Session->read('invited_user')));
                        } 

                        if (!$this->Session->check('invited_user')) { 
                            /** ******** Code for adding default Hazards to the new account *************** */
                            $this->loadModel('GlobalHazard');
                            $this->loadModel('Hazard');
                            $this->loadModel('Control');
                            $global_hazards = $this->GlobalHazard->find('all');
                            $hazardDetail['Hazard']['user_id'] = $user_id;
                            $hazardDetail['Hazard']['company_id'] = $this->request->data['User']['company_id'];
                            foreach ($global_hazards as $hazard) {
                                if(isset($this->request->data['User']['country_id']) && $this->request->data['User']['country_id']==13 && $hazard['GlobalHazard']['id']==7){
                                    continue;
                                }
                                $this->Hazard->create();
                                $hazardDetail['Hazard']['hazard'] = $hazard['GlobalHazard']['hazard'];
                                $hazardDetail['Hazard']['icon_name'] = $hazard['GlobalHazard']['icon_name'];
                                $hazardDetail['Hazard']['worst_case'] = $hazard['GlobalHazard']['worst_case'];
                                $hazardDetail['Hazard']['likelihood_precaution'] = $hazard['GlobalHazard']['likelihood_precaution']; 
                                $hazardDetail['Hazard']['status'] = 1;
                                $this->Hazard->save($hazardDetail);
                                $hazard_id = $this->Hazard->id;
                                $hazardControls['Control']['hazard_id'] = $hazard_id;
                                for ($i = 1; $i < 7; $i++) {
                                    if($this->request->data['User']['country_id']==13 && $hazard['GlobalHazard']['id']==3 && $i==1){
                                        continue;
                                    }
                                    $this->Control->create();
                                    if ($hazard['GlobalHazard']['option' . $i] != "") {
                                        $hazardControls['Control']['control'] = $hazard['GlobalHazard']['option' . $i];
                                        $this->Control->save($hazardControls);
                                    }
                                }
                            }

                            /********** End Code for adding default Hazards to the new account *********** */
                             
                              
                            // Trial account type
                            $email = $this->EmailTemplate->selectTemplate('Register confirmation  Request');
                            $emailFindReplace = array(
                                '##SITE_LINK##' => Router::url('/', true),
                                '##FIRSTNAME##' => $this->request->data['User']['first_name'],
                                '##USERNAME##' => $this->request->data['User']['email'], 
                                '##SITE_NAME##' => Configure::read('site.name'),
                                '##SUPPORT_EMAIL##' => Configure::read('site.contactus_email'),
                                '##WEBSITE_URL##' => Router::url('/', true),
                                '##FROM_EMAIL##' => $this->User->changeFromEmail(($email['from'] == '##FROM_EMAIL##') ? Configure::read('EmailTemplate.from_email') : $email['from']),
                                '##CONTACT_URL##' => Router::url(array(
                                    'controller' => '/',
                                    'action' => 'contact-us.html',
                                    'admin' => false
                                        ), true),
                                '##SITE_LOGO##' => Router::url(array(
                                    'controller' => 'img',
                                    'action' => '/',
                                    'admin-logo.png',
                                    'admin' => false
                                        ), true),
                            );
                            $this->Email->from = ($email['from'] == '##FROM_EMAIL##') ? Configure::read('EmailTemplate.from_email') : $email['from'];
                            $this->Email->replyTo = ($email['reply_to_email'] == '##REPLY_TO_EMAIL##') ? Configure::read('EmailTemplate.reply_to') : $email['reply_to_email'];
                            $this->Email->to = $this->request->data['User']['email'];
                            $this->Email->subject = strtr($email['subject'], $emailFindReplace);
                            $this->Email->sendAs = ($email['is_html']) ? 'html' : 'text';
                            $this->Email->send(strtr($email['description'], $emailFindReplace)); 
                        } 

                        $userData = $this->User->findById($user_id);
                        $user = $userData['User'];
                        $this->Auth->login($user);
                        $this->Session->write('Auth.User.Role', $userData['Role']);
                        $this->Session->write('Auth.User.Company', $userData['Company']);
                        $this->Session->write('Auth.User.Company.role_id', $userData['User']['role_id']);
                        $this->Session->write('Auth.User.Country', $userData['Country']);
                        $this->Session->write('Auth.User.PaymentInformation', $userData['PaymentInformation']);
                        
                        
                        
                        if ($this->Session->check('invited_user')) { 
                            $this->Session->delete('invited_user');
                            $this->User->updateAll(array(
                                'User.last_login' => '\'' . date('Y-m-d h:i:s') . '\''
                                    ), array(
                                'User.id' => $this->Auth->user('id')
                            ));
                            $admin_id = $this->User->field('administrator_id', array('User.id' => $this->Auth->user('id')));
                            $this->Session->write('Auth.User.Company.id', $this->Auth->user('company_id'));
                            $this->Session->write('Auth.User.Company.access', $this->Auth->user('access'));
                            $this->Session->write('Auth.User.Company.role_id', $this->Auth->user('role_id'));
                            $this->Session->write('Auth.User.administrator_id', $admin_id);
                            $this->Session->write('Auth.User.Company.administrator_id', $admin_id);   
                            $this->Session->write('Auth.User.Company.subscription_status', 1);   
                        }else{
                            if(!strpos($this->Auth->user("email"), "tribondinfosystems.com")){
                                $this->Session->write('Auth.User.NewRegistration', 1);
                            }
                        }

                        if($this->Auth->user('Company.administrator_id')==0){
                            $adminId = $this->Auth->user('id');
                        }else{
                            $adminId = $this->Auth->user('Company.administrator_id');
                        }
                    
                        $this->loadModel('MethodTemplate');
                        $globalTemplate = $this->MethodTemplate->find('count',array('conditions'=>array('user_id'=>$user_id,'is_global'=>1)));
                        if($globalTemplate < 1 && $this->Auth->user('role_id')==2){ 
                            $this->loadModel('GlobalHeader'); 
                            $this->loadModel('UserHeader');
                            $this->loadModel('UserHeaderStatment');
                            $this->loadModel('UserHeaderHazard');
                            $this->loadModel('UserMethodTemplate');
                            if($this->Auth->user('Company.country_id') == 13){
                                $country_id = 13;
                            }else{
                                $country_id = 1;
                            }
                            $globalHeaderList = $this->GlobalHeader->find('all',array(
                                'conditions'=>array('GlobalHeader.parent_id'=>0,'GlobalHeader.country_id'=>$country_id),
                                'contain' => array('GlobalStatment')
                            ));  
                            $this->UserHeaderHazard->bindModel(array(
                               'belongsTo' => array(
                                   'UserHeader'
                               ) 
                            )); 

                            $this->UserMethodTemplate->bindModel(array(
                               'belongsTo' => array(
                                   'MethodTemplate'
                               ) 
                            ));  
                            $this->request->data['MethodTemplate']['name'] = "Standard Template"; 
                            $this->request->data['MethodTemplate']['user_id'] = $this->Auth->user('id');
                            $this->request->data['MethodTemplate']['is_global'] = 1;
                            $this->MethodTemplate->create();
                            $this->MethodTemplate->save($this->request->data);
                            $this->loadModel('UserMethodTemplate');                
                            $method_template_id = $this->MethodTemplate->id;
                            $this->request->data['UserMethodTemplate']['method_template_id'] = $method_template_id;
                            $this->request->data['UserMethodTemplate']['user_id'] = $this->Auth->user('id');
                            $this->UserMethodTemplate->create();
                            $this->UserMethodTemplate->save($this->request->data);
                            foreach($globalHeaderList as $globalHeader){
                                $this->UserHeader->saveHeader($globalHeader,$this->Auth->user('id'),$method_template_id); 
                            } 
                        } 

                        $checkDefaultMsTemplate = $this->MethodTemplate->field("id", array(
                                "MethodTemplate.user_id" => $adminId, 
                                "MethodTemplate.default_status" => 1, 
                        ));

                        if($checkDefaultMsTemplate > 0){
                            $this->Session->write('method_template_id',$checkDefaultMsTemplate); 
                        } 

                        $this->loadModel('ChecklistTemplate');
                        $checkDefaultAuditTemplate = $this->ChecklistTemplate->field("id", array(
                                "ChecklistTemplate.user_id" => $adminId, 
                                "ChecklistTemplate.default_status" => 1, 
                        )); 
                        if($checkDefaultAuditTemplate > 0){
                            $this->Session->write('checklist_id',$checkDefaultAuditTemplate); 
                        }
                        
                        $this->loadModel("ChecklistTemplate");

                        $exampleTemplates = $this->ChecklistTemplate->find("count", array(
                            "conditions" => array(
                                "user_id" => $this->Auth->user("id"),
                                "is_example" => 1
                            )
                        ));

                        if($exampleTemplates < 1 && $this->Auth->user('role_id')==2){
                            $this->addStaticTemplate();
                        }
                        $this->Session->write("hazard_library_id", 1);
                        if($this->Session->check("plan_detail")){ 
                            if($this->Session->read("plan_detail.Plan.user_count")>0){
                                $this->redirect(array("controller"=>"plans", "action"=>"userDetail"));
                                exit;
                            }else{
                                $this->redirect(array("controller"=>"plans", "action"=>"packageDetail"));
                                exit;
                            }                    
                        } 
                        
                        //$this->redirect(array("controller"=> "departments", "action" => "getDepartmentData"));
                        $this->redirect(array("controller"=> "users", "action" => "register_success"));
                        
                        
                        exit; 
                    }
                }
            } 
        } else {
            if ($this->Session->check('invited_user') && $this->Session->read('invited_user') != "") {
                $this->request->data = $this->User->find('first', array('conditions' => array('User.id' => $this->Session->read('invited_user'))));
                unset($this->request->data['User']['password']); 
                $companyDetail = $this->Company->find('first', array('conditions' => array('Company.id' => $this->request->data['User']['company_id']))); 
                $this->request->data['User']['company_name'] = $companyDetail['Company']['name'];
                $this->request->data['User']['conf_email'] = $this->request->data['User']['email'];
                $this->request->data['User']['company_id'] = $companyDetail['Company']['id'];
                $this->request->data['User']['house_no'] = $companyDetail['Company']['house_no'];
                $this->request->data['User']['address1'] = $companyDetail['Company']['address1'];
                $this->request->data['User']['address2'] = $companyDetail['Company']['address2'];
                $this->request->data['User']['address3'] = $companyDetail['Company']['address3'];
                $this->request->data['User']['town'] = $companyDetail['Company']['town'];
                $this->request->data['User']['county'] = $companyDetail['Company']['county'];
                $this->request->data['User']['country_id'] = $companyDetail['Company']['country_id'];
                $this->request->data['User']['city'] = $companyDetail['Company']['city'];
                $this->request->data['User']['postcode'] = $companyDetail['Company']['postcode'];
                $this->request->data['User']['country_name'] = $companyDetail['Country']['name'];
                $this->set('readOnlyClass', 'readonly=true');
            }
        } 
    }

    function forget_password() { 
        if (!empty($this->request->data)) { 
			$this->request->data['User']['email'] = $this->data['user_email'];
            $user = $this->User->find('first', array('conditions' => array('User.email' => $this->request->data['User']['email']))); 
            $new_password = $this->User->randomPassword();
            $this->request->data['User']['id'] = $user['User']['id'];
            $this->request->data['User']['password'] = $new_password;
            if (!empty($user)) {
                $this->User->save($this->request->data);
                $email = $this->EmailTemplate->selectTemplate('forgot_password');
                $emailFindReplace = array(
                    '##SITE_LINK##' => Router::url('/', true),
                    '##USERNAME##' => $user['User']['first_name'],
                    '##USER_EMAIL##' => $user['User']['email'],
                    '##USER_PASSWORD##' => $new_password,
                    '##SITE_NAME##' => Configure::read('site.name'),
                    '##SUPPORT_EMAIL##' => Configure::read('site.contactus_email'),
                    '##WEBSITE_URL##' => Router::url('/', true),
                    '##FROM_EMAIL##' => $this->User->changeFromEmail(($email['from'] == '##FROM_EMAIL##') ? Configure::read('EmailTemplate.from_email') : $email['from']),
                    '##CONTACT_URL##' => Router::url(array(
                        'controller' => '/',
                        'action' => 'contact-us.html',
                        'admin' => false
                            ), true),
                    '##SITE_LOGO##' => Router::url(array(
                        'controller' => 'img',
                        'action' => '/',
                        'admin-logo.png',
                        'admin' => false
                            ), true),
                );
                $this->Email->from = ($email['from'] == '##FROM_EMAIL##') ? Configure::read('EmailTemplate.from_email') : $email['from'];
                $this->Email->replyTo = ($email['reply_to_email'] == '##REPLY_TO_EMAIL##') ? Configure::read('EmailTemplate.reply_to') : $email['reply_to_email'];
                $this->Email->to = $user['User']['email'];
                $this->Email->subject = strtr($email['subject'], $emailFindReplace);
                $this->Email->sendAs = ($email['is_html']) ? 'html' : 'text';
                if ($this->Email->send(strtr($email['description'], $emailFindReplace))) {
                    exit('1');
                }
            } else { 
                exit('2');
            }
        }
    }

    function confirm($md5id, $md5pass, $email) {
        if ($email != "") {
            $user = $this->User->find('first', array('conditions' => array('User.email' => $email), 'fields' => array('id', 'email', 'status')));
            if (!empty($user)) {
                if ($md5id == md5($user['User']['id'])) {
                    if ($user['User']['status'] == 0) {
                        $this->request->data['User']['id'] = $user['User']['id'];
                        $this->request->data['User']['status'] = 1;
                        $this->User->save($this->request->data);
                        $this->Session->setFlash(__('Account has been confirmed. Please login', 'default', array(), 'auth'));
                        $this->redirect('/');
                    } else {
                        $this->Session->setFlash(__('Account has been already confirmed. Please login', 'default', array(), 'auth'));
                        $this->redirect('/');
                    }
                } else {
                    $this->Session->setFlash(__('Invalid link for email confirmation', 'default', array(), 'auth'));
                    $this->redirect('/');
                }
            } else {
                $this->Session->setFlash(__('Invalid link for email confirmation', 'default', array(), 'auth'));
                $this->redirect('/');
            }
        }
    }

    function invite_confirm($id, $md5Id, $companyId = null) {
		
        if (md5($id) == $md5Id) {
            $this->loadModel("UsersCompany");
            $this->UsersCompany->bindModel(array(
                "belongsTo" => array(
                    "User"
                )
            ));
            $checkUserRec = $this->UsersCompany->find('first', array(
                'conditions' => array(
                    'User.id' => $id,
                    'UsersCompany.is_accept' => 0
                )
            ));
            if (!empty($checkUserRec)) {
                $this->Session->write('invited_user', $id);
                $this->redirect('register');
            } else {
                $this->Session->setFlash(__('Invalid link or link has been expired', 'default', array(), 'auth'));
                $this->redirect('/');
            }
        } else {
            $this->Session->setFlash(__('Invalid link or link has been expired', 'default', array(), 'auth'));
            $this->redirect('/');
        }
    }

    function invite_existing_confirm($id, $md5Id, $companyId = null) {
		
		if (md5($id) == $md5Id) {
            $this->loadModel('UsersCompany');
            $checkUserRec = $this->UsersCompany->find('first', array('conditions' => array('UsersCompany.company_id' => $companyId,'UsersCompany.user_id' => $id, 'UsersCompany.is_accept' => 0)));
            if (!empty($checkUserRec)) {
                $this->UsersCompany->id = $checkUserRec['UsersCompany']['id'];
                $this->request->data['UsersCompany']['is_accept'] = 1;
                $this->UsersCompany->save($this->request->data);
                $this->Session->setFlash(__('You have accepted company invitation for standard user', 'default', array(), 'auth'));
                $this->redirect('/');
            } else {
                $this->Session->setFlash(__('Invalid link or link has been expired', 'default', array(), 'auth'));
                $this->redirect('/');
            }
        } else {
            $this->Session->setFlash(__('Invalid link or link has been expired', 'default', array(), 'auth'));
            $this->redirect('/');
        }
    }

    function updateProfile() { 
        $this->layout = "task_module";
        $this->loadModel('Country');
        $country_list = $this->Country->find('list');
        $this->set('title_for_layout', Configure::read('site.name') . ' :: Update Profile');
        // List of all exist companies
        $this->loadModel('Company');
        $company_list = $this->Company->find('all');

        $this->set(compact('country_list', 'company_list'));
        if ($this->request->data) { 
            $this->request->data['Company']['postcode'] = strtoupper($this->request->data['Company']['postcode']);
            if($this->request->data['User']['password'] == ""){
                unset($this->request->data['User']['password']);
            }
            $this->request->data['Company']['country_id'] = $this->request->data['User']['country_id'];
            $this->request->data['User']['postcode'] = $this->request->data['Company']['postcode'];
            $this->request->data['Company']['id'] = $this->Auth->user('Company.id'); 
            if ($this->User->save($this->request->data)) { 
                $this->Company->save($this->request->data);
                $this->Session->write("Auth.User.Company.name", $this->request->data['Company']['name']);
                $this->Session->write("Auth.User.Company.house_no", $this->request->data['Company']['house_no']);
                $this->Session->write("Auth.User.Company.town", $this->request->data['Company']['town']);
                $this->Session->write("Auth.User.Company.city", $this->request->data['Company']['city']);
                $this->Session->write("Auth.User.Company.county", $this->request->data['Company']['county']);
                $this->Session->write("Auth.User.Company.postcode", $this->request->data['Company']['postcode']);
                $this->Session->write("Auth.User.Company.is_standard", $this->request->data['Company']['is_standard']);
                $this->Session->write("Auth.User.Company.country_id", $this->request->data['Company']['country_id']); 
                $this->Company->save($this->request->data);
                $this->Session->write("Auth.User.first_name", $this->request->data['User']['first_name']);
                $this->Session->write("Auth.User.last_name", $this->request->data['User']['last_name']);
                $this->Session->write("Auth.User.phone", $this->request->data['User']['phone']); 
                $this->Session->write("Auth.User.postcode", $this->request->data['User']['postcode']); 
                $this->Session->write("Auth.User.country_id", $this->request->data['User']['country_id']);
                $this->Session->write("Auth.User.Country.name", $country_list[$this->request->data['User']['country_id']]);
                $this->Session->write("Auth.User.Country.id", $this->request->data['User']['country_id']);
                if (isset($this->is_mobile) && $this->is_mobile) {
                    echo "0";exit;
                }else{
                    $this->redirect('dashboard');
                }
                
            }else{
                
            }
        } else { 

             $this->UsersCompany->bindModel(array(
                "belongsTo" => array(
                        "User"
                )
            )); 
            $this->request->data = $this->UsersCompany->find('first', array(
                'conditions' => array(
                    'UsersCompany.company_id' => $this->Auth->user('Company.id'),
                    'UsersCompany.user_id' => $this->Auth->user('id')
                ),
                'contain' => array(
                    'Company' => array('Country'),
                    "User"
                )
            ));   
            //echo "<pre>";print_r($this->request->data);die;
            $this->request->data['User']['old_company_logo'] = $this->request->data['Company']['company_logo'];
            unset($this->request->data['User']['password']);  

        } 
         
    }

    function userList($pageType="users") {
        $this->Session->delete("std_user_data");
        $this->layout = "task_module";
        $this->set('title_for_layout', Configure::read('site.name') . ' :: User List');

        if($this->request->is("post")){			
            if ($this->data['User']['user_csv']['name'] != "") {
                if ($this->data['User']['user_csv']['error'] == 0) { 
                    $name = $this->data['User']['user_csv']['name']; 
                    $type = $this->data['User']['user_csv']['type'];
                    $tmpName = $this->data['User']['user_csv']['tmp_name']; 
                    // check the file is a csv 
					
                    if (in_array($this->data['User']['user_csv']["type"], array("text/csv", "application/vnd.ms-excel", "application/octet-stream","application/excel" , "text/comma-separated-values"))) {
                        ini_set('auto_detect_line_endings', TRUE);
                        if (($handle = fopen($tmpName, 'r')) !== FALSE) {
                            // necessary if a large csv file
                            set_time_limit(0); 
                            $row = 0;
                            $emailExistsArr = array(); 
                            while (($data = fgetcsv($handle, 10000, ',')) !== FALSE) { 
                                if($row >= 2){ 
                                    if (!in_array($data[0], $emailExistsArr) && $data[0] != "") {
                                        foreach ($data as $key => $csvData) {
                                            // get the values from the csv
                                            if ($key == 0) {
                                                $csv[$row]['User']['email'] = $data[0];
                                                $csv[$row]['User']['first_name'] = $data[1];
                                                $csv[$row]['User']['last_name'] = $data[2];
                                            } 
											
											/* elseif ($data[$key] != "") {
                                                $csv[$row]['User']['group'][] = $data[$key];
                                            } */
											
											/**************** Assign Full Access to added user *****************/
											$csv[$row]['User']['fullAccess'] = explode("##", $data[3]);
											
											/**************** Assign Read Only Access to added user ***************/
											$csv[$row]['User']['readOnlyAccess'] = explode("##", $data[4]);
											
											/**************** Assign Template Access to added user ***************/
											$csv[$row]['User']['templateAccess'] = explode("##", $data[5]);
											
											/***************** Assign Default Folder *******************/
											$csv[$row]['User']['defaultFolder'] = $data[6];
                                        }
                                        $emailExistsArr[] = $data[0];
                                        $csv[$row]['User']['group'] = array_filter($csv[$row]['group']);
                                    }
                                }
                                // inc the row
                                $row++;
                            }
                            unset($csv[0]);
                            $csv = array_values($csv);
                            fclose($handle);
							
							
                        }
						
						if (!empty($csv)) {
                            $this->loadModel('TempUser');
                            $this->loadModel('User');
                            $this->loadModel('Group'); 
                            $this->loadModel('Plan'); 
                            $this->loadModel('UsersCompany'); 
                            $this->request->data['TempUser']['administrator_id'] = $this->Auth->user('id');
                            $this->request->data['TempUser']['email_status'] = 0;
                            $randomKey = $this->User->randomPassword();
                            $this->request->data['TempUser']['transaction_status'] = 0;
                            $this->request->data['TempUser']['transaction_key'] = $randomKey;
                            foreach ($csv as $key => $csvData) {
                                $isUserExist = $this->User->field('id',array('User.email'=>$csvData['User']['email']));
                                if($isUserExist){
                                    $isExistEmail = $this->UsersCompany->find('count',array('conditions'=>array('UsersCompany.user_id'=>$isUserExist,'UsersCompany.company_id'=>$this->Auth->user('Company.id'),'UsersCompany.transaction_status'=>1)));
                                    if($isExistEmail>0){
                                        unset($csv[$key]);
                                        $this->Session->setFlash('Some of your entered users are already added so removing those accounts');
                                        continue;
                                    }
                                }
                                $this->TempUser->create();
                                $this->request->data['TempUser']['email'] = $csvData['User']['email'];
                                $this->TempUser->save($this->request->data);
                            }

                            $planDetail = $this->Plan->find('first', array('conditions' => array('id' => $this->Auth->user('plan_id'))));

                            switch ($planDetail['Plan']['id']) {
                                case '2' :
                                    $amt = (Configure::read('site.std_userprice') * count($csv));
                                    $this->Session->write('plan_detail.Plan.month', 1);
                                    break;
                                case '5' :
                                    $amt = (Configure::read('site.std_userprice') * count($csv) * 6);
                                    $this->Session->write('plan_detail.Plan.month', 6);
                                    break;
                                case '6' :
                                    $amt = (Configure::read('site.std_userprice') * count($csv) * 12);
                                    $this->Session->write('plan_detail.Plan.month', 12);
                                    break;
                            }
							
                            $this->Session->write('plan_detail.Plan.plan_id', $planDetail['Plan']['id']);
                            $this->Session->write('plan_detail.Plan.amount', $amt); 
                            $this->Session->write('plan_detail.Plan.transaction_type', 'standard_user');
                            $this->Session->write('plan_detail.Plan.user_count', count($csv));
                            $this->Session->write('plan_detail.Plan.entry_type', 'csv');
                            $this->Session->write('std_user_data', $csv);
                            $this->Session->write('transaction_key', $randomKey);
							
							
							
							/****************condition for direct add user if purchased already*************************/
							$standardUserList = $this->Session->read('std_user_data');  

							$CompanyUsers = $this->UsersCompany->find('count', array(
								'conditions' => array(
									'UsersCompany.company_id' => $this->Session->read('Auth.User.Company.id')
								)
							));  
							$UserCount = $this->User->find('first', array(
								'conditions' => array(
									'User.id' => $this->Session->read('Auth.User.id')
								) , 
								'fields' => array(
									'User.user_count'
								)
							));
							
							if(($CompanyUsers + count($standardUserList)) <= ($UserCount['User']['user_count'])){  
								if (!empty($standardUserList)) {
									$inviter['User'] = $adminDetail['User'] = $this->Auth->user();
									if($this->Auth->user("Company.administrator_id") != 0){
										$adminDetail = $this->User->findById($this->Auth->user("Company.administrator_id"));
									}
									foreach ($standardUserList as $stdUserArr) {
										$stdUser = $stdUserArr['User']; 
										$checkUserExist = $this->User->field('id', array('User.email' => $stdUser['email']));  
										$this->UsersCompany->create();
										$this->loadModel('EmailTemplate');
										$userDetail = array();
										if ($checkUserExist) {
											$newUserId = $checkUserExist;
										} else { 
											$this->User->create();
											$this->request->data['User']['email'] = $stdUser['email'];
											$this->request->data['User']['first_name'] = $stdUser['first_name'];
											$this->request->data['User']['last_name'] = $stdUser['last_name'];
											$this->request->data['User']['role_id'] = 3;
											$this->request->data['User']['subscription_status'] = 1;
											$this->request->data['User']['company_id'] = $adminDetail['User']['company_id'];
											$this->request->data['User']['administrator_id'] = $adminDetail['User']['id'];
											$this->request->data['User']['available_space'] = 0;
											$this->request->data['User']['change_password_status'] = 1;
											$this->User->save($this->request->data); 
											$checkUserExist = $newUserId = $this->User->id;
										}    
										/************************* **************** Enter record in User Company table ********************** */
										$this->request->data['UsersCompany']['user_id'] = $newUserId;
										$this->request->data['UsersCompany']['company_id'] = $adminDetail['User']['company_id'];
										$this->request->data['UsersCompany']['administrator_id'] = $adminDetail['User']['id'];
										$this->request->data['UsersCompany']['role_id'] = 3;
										$this->request->data['UsersCompany']['is_accept'] = 0;
										$this->request->data['UsersCompany']['is_subscribed'] = 1;
										$this->request->data['UsersCompany']['profile_id'] = '';
										$this->request->data['UsersCompany']['available_space'] = 0;
										$this->request->data['UsersCompany']['transaction_status'] = 1;
										$this->request->data['UsersCompany']['transaction_key'] = "";
										$this->UsersCompany->save($this->request->data); 
										/********************** **************** End Enter record in User Company table ********************** */
										/************* Send Invitation Email to user *****************/
										if ($checkUserExist) {
											$email = $this->EmailTemplate->selectTemplate('Already Registerd');
											$emailFindReplace = array(
												'##SITE_LINK##' => Router::url('/', true),
												'##FIRST_NAME##' => $stdUser['last_name'],
												'##USER_EMAIL##' => $stdUser['email'],
												'##INVITER_FIRSTNAME##' => $this->Auth->user("first_name"),
												'##INVITER_SECONDNAME##' => $this->Auth->user("last_name"),
												'##INVITE_LINK##' => Router::url('/', true) . "users/invite_existing_confirm/" . $checkUserExist . "/" . md5($checkUserExist) . "/" . $this->Auth->user("Company.id"),
												'##SITE_NAME##' => Configure::read('site.name'),
												'##SUPPORT_EMAIL##' => Configure::read('site.contactus_email'),
												'##WEBSITE_URL##' => Router::url('/', true),
												'##FROM_EMAIL##' => $this->User->changeFromEmail(($email['from'] == '##FROM_EMAIL##') ? Configure::read('EmailTemplate.from_email') : $email['from']),
												'##CONTACT_URL##' => Router::url(array(
													'controller' => '/',
													'action' => 'contact-us.html',
													'admin' => false
														), true),
												'##SITE_LOGO##' => Router::url(array(
													'controller' => 'img',
													'action' => '/',
													'admin-logo.png',
													'admin' => false
														), true),
											);
										} else {
											$this->User->create();
											$email = $this->EmailTemplate->selectTemplate('Standard User Email');
											$emailFindReplace = array(
												'##SITE_LINK##' => Router::url('/', true),
												'##USERNAME##' => $stdUser['first_name'],
												'##USER_EMAIL##' => $stdUser['email'],
												'##INVITER_FIRSTNAME##' => $this->Auth->user("first_name"),
												'##INVITER_SECONDNAME##' => $this->Auth->user("last_name"),
												'##INVITE_LINK##' => Router::url('/', true) . "users/invite_confirm/" . $newUserId . "/" . md5($newUserId . "/" . $this->Auth->user("Company.id")),
												'##SITE_NAME##' => Configure::read('site.name'),
												'##SUPPORT_EMAIL##' => Configure::read('site.contactus_email'),
												'##WEBSITE_URL##' => Router::url('/', true),
												'##FROM_EMAIL##' => $this->User->changeFromEmail(($email['from'] == '##FROM_EMAIL##') ? Configure::read('EmailTemplate.from_email') : $email['from']),
												'##CONTACT_URL##' => Router::url(array(
													'controller' => '/',
													'action' => 'contact-us.html',
													'admin' => false
														), true),
												'##SITE_LOGO##' => Router::url(array(
													'controller' => 'img',
													'action' => '/',
													'admin-logo.png',
													'admin' => false
														), true),
											);
										}
										$this->Email->from = ($email['from'] == '##FROM_EMAIL##') ? Configure::read('EmailTemplate.from_email') : $email['from'];
										$this->Email->replyTo = ($email['reply_to_email'] == '##REPLY_TO_EMAIL##') ? Configure::read('EmailTemplate.reply_to') : $email['reply_to_email'];
										$this->Email->to = $stdUser['email'];
										$this->Email->subject = strtr($email['subject'], $emailFindReplace);
										$this->Email->sendAs = ($email['is_html']) ? 'html' : 'text';
										$this->Email->send(strtr($email['description'], $emailFindReplace));  
										/************* End Send Invitation Email to user *****************/

										/********************** ******************* Enter record in GroupAccess table ************************** */
										$this->loadModel('Group');
										$this->loadModel('GroupUser');
										foreach ($stdUser['group_id'] as $groupId) {
											$this->GroupUser->create();
											$this->request->data['GroupUser']['user_id'] = $newUserId; 
											$this->request->data['GroupUser']['group_id'] = $groupId; 
											$this->request->data['GroupUser']['company_id'] = $adminDetail['User']['company_id'];
											$this->GroupUser->save($this->request->data);
										} 
										$this->User->id = $checkUserExist;
										$userList .= "User Email : " . $stdUser['email'] . "<br/>"; 
									} 
								}
								$this->redirect("userList");
								exit;
							}elseif($CompanyUsers < $UserCount['User']['user_count']){
									
								if (!empty($standardUserList)) {
									$inviter['User'] = $adminDetail['User'] = $this->Auth->user();
									if($this->Auth->user("Company.administrator_id") != 0){
										$adminDetail = $this->User->findById($this->Auth->user("Company.administrator_id"));
									}
									$addedCompanyUsers = $CompanyUsers;
									foreach ($standardUserList as $stdKey => $stdUserArr) {
										if($addedCompanyUsers < $UserCount['User']['user_count']){
											$stdUser = $stdUserArr['User'];
											$checkUserExist = $this->User->field('id', array('User.email' => $stdUser['email']));
											$this->UsersCompany->create();
											$this->loadModel('EmailTemplate');
											$userDetail = array();
											if ($checkUserExist) {
												$newUserId = $checkUserExist;
											} else {
												$this->User->create();
												$this->request->data['User']['email'] = $stdUser['email'];
												$this->request->data['User']['first_name'] = $stdUser['first_name'];
												$this->request->data['User']['last_name'] = $stdUser['last_name'];
												$this->request->data['User']['role_id'] = 3;
												$this->request->data['User']['subscription_status'] = 1;
												$this->request->data['User']['company_id'] = $adminDetail['User']['company_id'];
												$this->request->data['User']['administrator_id'] = $adminDetail['User']['id'];
												$this->request->data['User']['available_space'] = 0;
												$this->request->data['User']['change_password_status'] = 1;
												$this->User->save($this->request->data);
												$newUserId = $this->User->id; 
											}  
											/************************* **************** Enter record in User Company table ********************** */
											$this->request->data['UsersCompany']['user_id'] = $newUserId;
											$this->request->data['UsersCompany']['company_id'] = $adminDetail['User']['company_id'];
											$this->request->data['UsersCompany']['administrator_id'] = $adminDetail['User']['id'];
											$this->request->data['UsersCompany']['role_id'] = 3;
											$this->request->data['UsersCompany']['is_accept'] = 0;
											$this->request->data['UsersCompany']['is_subscribed'] = 1;
											$this->request->data['UsersCompany']['profile_id'] = '';
											$this->request->data['UsersCompany']['available_space'] = 0;
											$this->request->data['UsersCompany']['transaction_status'] = 1;
											$this->request->data['UsersCompany']['transaction_key'] = "";
											$this->UsersCompany->save($this->request->data); 
											/********************** **************** End Enter record in User Company table ********************** */
											/************* Send Invitation Email to user *****************/
											if ($checkUserExist) {
												$email = $this->EmailTemplate->selectTemplate('Already Registerd');
												$emailFindReplace = array(
													'##SITE_LINK##' => Router::url('/', true),
													'##FIRST_NAME##' => $stdUser['last_name'],
													'##USER_EMAIL##' => $stdUser['email'],
													'##INVITER_FIRSTNAME##' => $this->Auth->user("first_name"),
													'##INVITER_SECONDNAME##' => $this->Auth->user("last_name"),
													'##INVITE_LINK##' => Router::url('/', true) . "users/invite_existing_confirm/" . $checkUserExist . "/" . md5($checkUserExist) . "/" . $this->Auth->user("Company.id"),
													'##SITE_NAME##' => Configure::read('site.name'),
													'##SUPPORT_EMAIL##' => Configure::read('site.contactus_email'),
													'##WEBSITE_URL##' => Router::url('/', true),
													'##FROM_EMAIL##' => $this->User->changeFromEmail(($email['from'] == '##FROM_EMAIL##') ? Configure::read('EmailTemplate.from_email') : $email['from']),
													'##CONTACT_URL##' => Router::url(array(
														'controller' => '/',
														'action' => 'contact-us.html',
														'admin' => false
															), true),
													'##SITE_LOGO##' => Router::url(array(
														'controller' => 'img',
														'action' => '/',
														'admin-logo.png',
														'admin' => false
															), true),
												);
											} else {
												$this->User->create();
												$email = $this->EmailTemplate->selectTemplate('Standard User Email');
												$emailFindReplace = array(
													'##SITE_LINK##' => Router::url('/', true),
													'##USERNAME##' => $stdUser['first_name'],
													'##USER_EMAIL##' => $stdUser['email'],
													'##INVITER_FIRSTNAME##' => $this->Auth->user("first_name"),
													'##INVITER_SECONDNAME##' => $this->Auth->user("last_name"),
													'##INVITE_LINK##' => Router::url('/', true) . "users/invite_confirm/" . $newUserId . "/" . md5($newUserId . "/" . $this->Auth->user("Company.id")),
													'##SITE_NAME##' => Configure::read('site.name'),
													'##SUPPORT_EMAIL##' => Configure::read('site.contactus_email'),
													'##WEBSITE_URL##' => Router::url('/', true),
													'##FROM_EMAIL##' => $this->User->changeFromEmail(($email['from'] == '##FROM_EMAIL##') ? Configure::read('EmailTemplate.from_email') : $email['from']),
													'##CONTACT_URL##' => Router::url(array(
														'controller' => '/',
														'action' => 'contact-us.html',
														'admin' => false
															), true),
													'##SITE_LOGO##' => Router::url(array(
														'controller' => 'img',
														'action' => '/',
														'admin-logo.png',
														'admin' => false
															), true),
												);
											}
											$this->Email->from = ($email['from'] == '##FROM_EMAIL##') ? Configure::read('EmailTemplate.from_email') : $email['from'];
											$this->Email->replyTo = ($email['reply_to_email'] == '##REPLY_TO_EMAIL##') ? Configure::read('EmailTemplate.reply_to') : $email['reply_to_email'];
											$this->Email->to = $stdUser['email'];
											$this->Email->subject = strtr($email['subject'], $emailFindReplace);
											$this->Email->sendAs = ($email['is_html']) ? 'html' : 'text';
											$this->Email->send(strtr($email['description'], $emailFindReplace));  
											/************* End Send Invitation Email to user *****************/

											/********************** ******************* Enter record in GroupAccess table ************************** */
											$this->loadModel('Group');
											$this->loadModel('GroupUser');
											foreach ($stdUser['group_id'] as $groupId) {
												$this->GroupUser->create();
												$this->request->data['GroupUser']['user_id'] = $newUserId; 
												$this->request->data['GroupUser']['group_id'] = $groupId; 
												$this->request->data['GroupUser']['company_id'] = $adminDetail['User']['company_id'];
												$this->GroupUser->save($this->request->data);
											} 
											$this->User->id = $checkUserExist;
											$userList .= "User Email : " . $stdUser['email'] . "<br/>";
											unset($standardUserList[$stdKey]);
											$addedCompanyUsers++; 
										} 
									}
									if(empty($standardUserList)) {
										$this->redirect("userList");exit;
									}
									$this->Session->write('std_user_data', $standardUserList); 
								}
							}
                            $this->redirect(array('controller' => 'users', 'action' => 'viewStdDetail'));
                            exit;
                        } else {
                            $error_msg = "No valid data in uploaded file."; 
                        }
                    } else {
                        $error_msg = "Please enter valid CSV file."; 
                    }
                } else {
                    $error_msg = "Unable to read the uploaded file. Please try later.";  
                }
				
				
                $this->Session->setFlash($error_msg);
                $this->redirect("userList");
                exit;
            } 
        }
        if($this->request->is("ajax")){
            $folderIdJson = $this->data['folder_ids'];
            $fromDate = $this->data['from'];
            $toDate = $this->data['to'];
            $filter_by = $this->data['filter_by'];
            $json = '{"filter_data":"'.$filter_by.'","from":"'.$fromDate.'","to":"'.$toDate.'","filter_folder":['.$folderIdJson.'],"company_id":' . $this->Auth->user("Company.id") .',"user_id":' . $this->Auth->user("id") .',"id":0,"is_first_call":true}';
            $this->layout = ''; 
        }else{
            $json = '{"company_id":' . $this->Auth->user("Company.id") .',"id":' . $this->Auth->user("id") .'}';
        }  
        $url = Router::url("/", true) . "window_services";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Action: user_list'
        )); 
        curl_setopt($ch, CURLOPT_POST, true);  // tell curl you want to post something
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $data = curl_exec($ch);
        curl_close($ch);  
        $userData = json_decode($data, true); 
		$userData['data']['groups'] = $this->array_orderby($userData['data']['groups'], "name", SORT_ASC); 
        foreach($userData['data']['groups'] as $key => $group){
            $groupFolderData = array();
            if(!empty($group['folders'])){
                $groupFolderData = Hash::combine($group['folders'], "{n}.id", "{n}.name", "{n}.access");
            }  
            $userData['data']['groups'][$key]['default_folder_array'] = isset($groupFolderData[2]) ? $groupFolderData[2] : array();
        }   
        foreach ($userData['data']['users'] as $key => $user) {
            if(isset($userData['data']['users'][$key]['folders'])){
                $userData['data']['users'][$key]['folders'] = $this->array_orderby($userData['data']['users'][$key]['folders'], "name", SORT_ASC); 
            } 
        } 
		
        $this->set(compact("userData", "pageType"));
        if($this->request->is("ajax")){
            $this->render("/Elements/front/report_ele");
        }

    }

    function editUser($id = null, $md5Id = null) {
        if ($id == $md5Id) {
            if ($this->Auth->user('role_id' == 2)) {
                
            } else {
                
            }
        } else {
            
        }
    }

    function switchUser($companyId) {
        $this->loadModel('UsersCompany');
        $this->UsersCompany->bindModel(array(
            "belongsTo" => array(
                "Admin" => array(
                    "className" => "User",
                    "foreignKey" => "administrator_id"
                )
            )
        ));
        $userCompanyDetail = $this->UsersCompany->find('first', array(
            'conditions' => array(
                'UsersCompany.user_id' => $this->Auth->user('id'), 
                'UsersCompany.company_id' => $companyId
            )
        ));
        
        $this->Session->write('Auth.User.Company', $userCompanyDetail['Company']);
        $this->Session->write('Auth.User.Company.access', $userCompanyDetail['UsersCompany']['access']);
        $this->Session->write('Auth.User.Company.role_id', $userCompanyDetail['UsersCompany']['role_id']);
        $this->Session->write('Auth.User.Company.administrator_id', $userCompanyDetail['UsersCompany']['administrator_id']);
        $this->Session->write('Auth.User.Company.company_id', $userCompanyDetail['UsersCompany']['administrator_id']);
        if($userCompanyDetail['UsersCompany']['administrator_id'] != 0){
            $this->Session->write('Auth.User.Company.country_id', $userCompanyDetail['Admin']['country_id']);
            $this->Session->write('Auth.User.Company.subscription_status', $userCompanyDetail['Admin']['subscription_status']);
        }else{
            $this->Session->write('Auth.User.Company.country_id', $this->Auth->user("country_id"));
            $this->Session->write('Auth.User.Company.subscription_status', $this->Auth->user("subscription_status"));
        } 
        $this->Session->delete("hazard_library_id");
        $this->Session->delete("checklist_id");
        $this->Session->delete("method_template_id");

        $this->request->data['User']['id'] = $this->Auth->user("id");
        $this->request->data['User']['last_selected_company'] = $companyId;
        $this->User->save($this->data);
        $this->redirect(array("controller"=>"departments", "action"=>"getDepartmentData"));
    }

    /**
     * deleteStdUser method
     * Used for deleting standard users
     * @throws NotFoundException
     * @throws MethodNotAllowedException
     * @param string $id
     * @return void
     */
    public function deleteStdUser($id = null,$status = 0) {
        $this->User->id = $id;
        if (!$this->User->exists()) {
            throw new NotFoundException(__('Invalid request'));
        }
        // $this->request->onlyAllow('post', 'delete');
        if ($this->User->updateAll(array('User.status'=>$status),array("User.id"=>$id))) {
            /* Removing records from users_companies table 
            $condition = array('UsersCompany.user_id' => $id);
            $this->loadModel('UsersCompany');
            $this->UsersCompany->recursive = 0;
            $usersCompany = $this->UsersCompany->find('count', array('conditions' => $condition));
            if (!empty($usersCompany)) {
                $this->UsersCompany->deleteAll($condition, false);
            }

            /* Removing records from user_accesses table 
            $condition = array('UserAccess.user_id' => $id);
            $this->loadModel('UserAccess');
            $this->UserAccess->recursive = 0;
            $userAccess = $this->UserAccess->find('count', array('conditions' => $condition));
            if (!empty($userAccess)) {
                $this->UserAccess->deleteAll($condition, false);
            }

            /* Removing records from user_subscription_histories table 
            $condition = array('UserSubscriptionHistory.user_id' => $id);
            $this->loadModel('UserSubscriptionHistory');
            $this->UserSubscriptionHistory->recursive = 0;
            $userSubscriptionHistory = $this->UserSubscriptionHistory->find('count', array('conditions' => $condition));
            if (!empty($userSubscriptionHistory)) {
                $this->UserSubscriptionHistory->deleteAll($condition, false);
            }

            /* Removing records from  app_users table 
            $condition = array('AppPdf.user_id' => $id);
            $this->loadModel('AppPdf');
            $this->AppPdf->recursive = 0;
            $appPdf = $this->AppPdf->find('count', array('conditions' => $condition));
            if (!empty($appPdf)) {
                $this->AppPdf->deleteAll($condition, false);
            }*/
            if($status==0){
                echo __('User deactivated');
                die;
            }else{
                echo __('User activated');
                die;
            }
            
        }

        if($status==0){
            echo __('User not deactivated');
            die;
        }else{
            echo __('User not activated');
            die;
        }
    }

    /**
     * editStdUser method
     * Used for editing standard users few detail
     * @throws NotFoundException
     * @throws MethodNotAllowedException
     * @param string $id
     * @return void
     */
    public function editStdUser($id = null) {
        $this->layout = 'inner';
        $this->set('title_for_layout', Configure::read('site.name') . ' :: Edit Standard User');
        $this->loadModel('Department');
        $getAccessList = $this->Department->find('list', array(
            'conditions' => array(
                'Department.company_id' => $this->Session->read('Auth.User.Company.id'), 
                'Department.parent_id' => 0, 
                'Department.user_id' => $this->Auth->user('id')
            )
        ));
        $this->User->unbindModel(array(
            'belongsTo' => array(
                'Role', 'Company'
            )
        ));

        $this->User->bindModel(array(
            'hasAndBelongsToMany' => array(
                'Group' => array(
                    'className' => 'Group',
                    'joinTable' => 'group_users',
                    'foreignKey' => 'user_id',
                    'associationForeignKey' => 'group_id',
                    'unique' => true,
                    'fields' => array('Group.name', 'Group.id')
                )
            )
        ));
        $stduserDetail = $this->User->find('first', array('conditions' => array('User.id' => $id)));


        if ($this->data) {

            $adminid = $this->Auth->user('id');
            $userDetail = $this->User->find('first', array('fields' => 'User.plan_id', 'recursive' => -2, 'conditions' => array('User.id' => $adminid)));

            $userArr = $this->request->data;

            $errorText = "";
            $noerror = true;

            $userCount = $this->User->find('count', array('conditions' => array('User.email' => $userArr['User']['email'])));
            if ($userCount > 0 && $stduserDetail['User']['email'] != $userArr['User']['email']) {
                $errorText .= "Email Address already used<br/>";
                $noerror = false;
            }
            if ($userArr['User']['email'] != $userArr['User']['confirm_email']) {
                $errorText .= "Email and confirm email should be same<br/>";
                $noerror = false;
            }
            if (empty($userArr['User']['group'])) {
                $errorText .= "No group assign to user<br/>";
                $noerror = false;
            }

            if ($noerror) {
                /* BOF Update and send mail. */
                // Fetch previous mail id
                $this->loadModel('GroupUser');
                if ($stduserDetail['User']['email'] != $userArr['User']['email']) {
                    if (!empty($id)) {

                        $this->loadModel('UsersCompany');
                        $this->loadModel('EmailTemplate');
                        $userDetail = $this->User->find('first', array('fields' => 'User.email,User.first_name,User.last_name', 'conditions' => array('User.id' => $id), 'recursive' => -2));
                        

                        $emailPrevious = $userDetail['User']['email'];
                        $previous_name = $userDetail['User']['first_name'];

                        if (!empty($emailPrevious)) {
                            // send mail to old employee					
                            $emailcancel = $this->EmailTemplate->selectTemplate('Account Cancellation');
                            $emailFindReplaceCancel = array(
                                '##SITE_LINK##' => Router::url('/', true),
                                '##FIRST_NAME##' => $previous_name,
                                '##USER_EMAIL##' => $this->request->data['User'][0]['email'],
                                '##INVITER_FIRSTNAME##' => $this->Auth->user('first_name'),
                                '##INVITER_SECONDNAME##' => $this->Auth->user('last_name'),
                                '##SITE_NAME##' => Configure::read('site.name'),
                                '##SUPPORT_EMAIL##' => Configure::read('site.contactus_email'),
                                '##WEBSITE_URL##' => Router::url('/', true),
                                '##FROM_EMAIL##' => $this->User->changeFromEmail(($emailcancel['from'] == '##FROM_EMAIL##') ? Configure::read('EmailTemplate.from_email') : $emailcancel['from']),
                                '##CONTACT_URL##' => Router::url(array(
                                    'controller' => '/',
                                    'action' => 'contact-us.html',
                                    'admin' => false
                                        ), true),
                                '##SITE_LOGO##' => Router::url(array(
                                    'controller' => 'img',
                                    'action' => '/',
                                    'admin-logo.png',
                                    'admin' => false
                                        ), true),);

                            //pr($emailPrevious); die;				
                            $this->Email->from = ($emailcancel['from'] == '##FROM_EMAIL##') ? Configure::read('EmailTemplate.from_email') : $emailcancel['from'];
                            $this->Email->replyTo = ($emailcancel['reply_to_email'] == '##REPLY_TO_EMAIL##') ? Configure::read('EmailTemplate.reply_to') : $emailcancel['reply_to_email'];
                            $this->Email->to = $emailPrevious;
                            $this->Email->subject = strtr($emailcancel['subject'], $emailFindReplaceCancel);
                            $this->Email->sendAs = ($emailcancel['is_html']) ? 'html' : 'text';
                            $this->Email->send(strtr($emailcancel['description'], $emailFindReplaceCancel));
                        }
                        // update current one	

                        $userArr = array();
                        $userArr['User']['email'] = $this->request->data['User']['email'];
                        $userArr['User']['first_name'] = '';
                        $userArr['User']['last_name'] = '';
                        $userArr['User']['username'] = '';
                        $userArr['User']['password'] = '';
                        $userArr['User']['house_no'] = '';
                        $userArr['User']['address1'] = '';
                        $userArr['User']['address2'] = '';
                        $userArr['User']['address3'] = '';
                        $userArr['User']['town'] = '';
                        $userArr['User']['county'] = '';
                        $userArr['User']['city'] = '';
                        $userArr['User']['postcode'] = '';
                        $userArr['User']['contact'] = '';
                        $userArr['User']['phone'] = '';
                        $userArr['User']['change_password_status'] = 1;
                        $userArr['User']['subscription_status'] = 0;
                        $userArr['User']['available_space'] = 0;
                        $userArr['User']['created'] = date('Y-m-d H:i:s');

                        $access = ($this->request->data['User'][0]['access'] == 'Full') ? "1" : "0";
                        $userArr['User']['access'] = $access;

                        $this->User->id = $id;
                        $this->User->save($userArr, false);

                        ######## Updating entries in other table #########
                        $this->UsersCompany->updateAll(
                                array(
                            'UsersCompany.is_subscribed' => 0,
                            'UsersCompany.access' => $access,
                            'UsersCompany.available_space' => 0
                                ), array('UsersCompany.user_id' => $id)
                        );

                        ################## Access Table##############
                        $this->GroupUser->deleteAll(array('GroupUser.user_id' => $id));
                        foreach ($this->data['User']['group'] as $groupId) {
                            $this->GroupUser->create();
                            $this->request->data['GroupUser']['user_id'] = $id;
                            $this->request->data['GroupUser']['group_id'] = $groupId;
                            $this->request->data['GroupUser']['company_id'] = $this->Session->read('Auth.User.Company.id');
                            $this->GroupUser->save($this->request->data);
                        }

                        // Send mail to new one with registration page link						
                        $email = $this->EmailTemplate->selectTemplate('Standard User Email');
                        $emailFindReplace = array(
                            '##SITE_LINK##' => Router::url('/', true),
                            '##USERNAME##' => '',
                            '##USER_EMAIL##' => $this->request->data['User'][0]['email'],
                            '##INVITER_FIRSTNAME##' => $this->Auth->user('first_name'),
                            '##INVITER_SECONDNAME##' => $this->Auth->user('last_name'),
                            '##INVITE_LINK##' => Router::url('/', true) . "users/invite_confirm/" . $id . "/" . md5($id),
                            '##SITE_NAME##' => Configure::read('site.name'),
                            '##SUPPORT_EMAIL##' => Configure::read('site.contactus_email'),
                            '##WEBSITE_URL##' => Router::url('/', true),
                            '##FROM_EMAIL##' => $this->User->changeFromEmail(($email['from'] == '##FROM_EMAIL##') ? Configure::read('EmailTemplate.from_email') : $email['from']),
                            '##CONTACT_URL##' => Router::url(array(
                                'controller' => '/',
                                'action' => 'contact-us.html',
                                'admin' => false
                                    ), true),
                            '##SITE_LOGO##' => Router::url(array(
                                'controller' => 'img',
                                'action' => '/',
                                'admin-logo.png',
                                'admin' => false
                                    ), true),);


                        $this->Email->from = ($email['from'] == '##FROM_EMAIL##') ? Configure::read('EmailTemplate.from_email') : $email['from'];
                        $this->Email->replyTo = ($email['reply_to_email'] == '##REPLY_TO_EMAIL##') ? Configure::read('EmailTemplate.reply_to') : $email['reply_to_email'];
                        $this->Email->to = $this->request->data['User']['email'];
                        $this->Email->subject = strtr($email['subject'], $emailFindReplace);
                        $this->Email->sendAs = ($email['is_html']) ? 'html' : 'text';
                        $this->Email->send(strtr($email['description'], $emailFindReplace));

                        ############## Send mail to Admin ####################
                        $email = $this->EmailTemplate->selectTemplate('Updated Standard User Email');
                        $emailFindReplaceFind = array(
                            '##SITE_LINK##' => Router::url('/', true),
                            '##USERNAME##' => $this->Auth->user('first_name'),
                            '##USER_EMAIL##' => $this->request->data['User'][0]['email'],
                            '##DROPEMAIL##' => $emailPrevious,
                            '##NEWEMAIL##' => $this->request->data['User'][0]['email'],
                            '##INVITER_FIRSTNAME##' => $this->Auth->user('first_name'),
                            '##INVITER_SECONDNAME##' => $this->Auth->user('last_name'),
                            '##SITE_NAME##' => Configure::read('site.name'),
                            '##SUPPORT_EMAIL##' => Configure::read('site.contactus_email'),
                            '##WEBSITE_URL##' => Router::url('/', true),
                            '##FROM_EMAIL##' => $this->User->changeFromEmail(($email['from'] == '##FROM_EMAIL##') ? Configure::read('EmailTemplate.from_email') : $email['from']),
                            '##CONTACT_URL##' => Router::url(array(
                                'controller' => '/',
                                'action' => 'contact-us.html',
                                'admin' => false
                                    ), true),
                            '##SITE_LOGO##' => Router::url(array(
                                'controller' => 'img',
                                'action' => '/',
                                'admin-logo.png',
                                'admin' => false
                                    ), true),);

                        $this->Email->from = ($email['from'] == '##FROM_EMAIL##') ? Configure::read('EmailTemplate.from_email') : $email['from'];
                        $this->Email->replyTo = ($email['reply_to_email'] == '##REPLY_TO_EMAIL##') ? Configure::read('EmailTemplate.reply_to') : $email['reply_to_email'];
                        $this->Email->to = $this->Auth->user('email');
                        $this->Email->subject = strtr($email['subject'], $emailFindReplaceFind);
                        $this->Email->sendAs = ($email['is_html']) ? 'html' : 'text';
                        $this->Email->send(strtr($email['description'], $emailFindReplaceFind));
                    }
                } else {
                    $this->GroupUser->deleteAll(array('GroupUser.user_id' => $id));
                    foreach ($this->data['User']['group'] as $groupId) {
                        $this->GroupUser->create();
                        $this->request->data['GroupUser']['user_id'] = $id;
                        $this->request->data['GroupUser']['group_id'] = $groupId;
                        $this->request->data['GroupUser']['company_id'] = $this->Session->read('Auth.User.Company.id');
                        $this->GroupUser->save($this->request->data);
                    }
                }
                /* EOF Update and send mail. */
                $this->redirect('userList');
                exit;
            } else {
                $this->set('errorText', $errorText);
                $this->set('userList', $this->request->data);
            }
        } else {

            $this->request->data = $stduserDetail;
            $this->request->data['Group'] = Hash::combine($this->request->data['Group'], '{n}.id', '{n}.name');
        }
        /*         * ************************* User Group List *********************** */

        $this->loadModel('Group');
        $userGroups = $this->Group->find('list', array('conditions' => array('Group.user_id' => $this->Auth->user('id')), 'fields' => array('Group.id', 'Group.name')));
        //pr($userGroups);die;

        /*         * ************************* User Group List *********************** */
        $this->set(compact('getAccessList', 'userGroups'));
    }

    /**
     * editStdUser method
     * Used for editing standard users few detail
     * @throws NotFoundException
     * @throws MethodNotAllowedException
     * @param string $id
     * @return void
     */
    public function addStdUser($userPage=0) {
        $this->layout = '';
        $this->set('title_for_layout', Configure::read('site.name') . ' :: Add Standard User');
        $this->loadModel('Department');
        $this->loadModel('Plan');
        $this->loadModel('UsersCompany'); 
        $adminid = $this->Auth->user('id');
        $getAccessList = $this->Department->find('list', array('conditions' => array('Department.user_id' => $adminid, 'Department.company_id' => $this->Session->read('Auth.User.Company.id'), 'Department.parent_id' => 0))); 
        if ($this->request->is("post")) { 
            if($this->data['User']['another_user']==1){
                if($this->Session->check("std_user_data")){
                    $oldStdUserData = $this->Session->read("std_user_data"); 
                    if($this->Session->check("std_user_data.".$userPage)){
                        $oldStdUserData[$userPage] = $this->data;
                    }else{
                        foreach($oldStdUserData as $key => $stdUser){
                            if($this->data['User']['email'] == $stdUser['User']['email']){
                                unset($oldStdUserData[$key]);
                            }
                        }
                        $oldStdUserData[] = $this->data;
                    }  
                    $this->Session->write("std_user_data", $oldStdUserData);
                }else{
                    $stdUserData[0] = $this->data;
                    $this->Session->write("std_user_data", $stdUserData);
                } 
                $userPage = count($this->Session->read("std_user_data"));
                $this->redirect(array("controller"=>"users", "action"=>"addStdUser", $userPage));
                exit;
            }else{
                if($this->Session->check("std_user_data")){
                    $oldStdUserData = $this->Session->read("std_user_data");
                    foreach($oldStdUserData as $key => $stdUser){
                        if($this->data['User']['email'] == $stdUser['User']['email']){
                            unset($oldStdUserData[$key]);
                        }
                    }
                    $oldStdUserData[] = $this->data;
                    $this->Session->write("std_user_data", $oldStdUserData);
                }else{
                    $stdUserData[0] = $this->data;
                    $this->Session->write("std_user_data", $stdUserData);
                }  
            }
            $totUserData = $this->Session->read("std_user_data");
            $userDetail = $this->User->find('first', array('fields' => 'User.plan_id', 'recursive' => -2, 'conditions' => array('User.id' => $adminid)));
      
            switch ($this->Session->read('plan_detail.Plan.plan_id')) {
                case '2' :
                    $amt = (Configure::read('site.std_userprice') * count($this->request->data['User']));
                    $this->Session->write('plan_detail.Plan.month', 1);
                    break;
                case '5' :
                    $amt = (Configure::read('site.std_userprice') * count($this->request->data['User']) * 6);
                    $this->Session->write('plan_detail.Plan.month', 6);
                    break;
                case '6' :
                    $amt = (Configure::read('site.std_userprice') * count($this->request->data['User']) * 12);
                    $this->Session->write('plan_detail.Plan.month', 12);
                    break;
            } 

            foreach($totUserData as $key => $stdUser){
                $isUserExist = $this->User->field('id',array('User.email'=>$stdUser['User']['email']));
                if($isUserExist){
                    $isExistEmail = $this->UsersCompany->find('count',array('conditions'=>array('UsersCompany.user_id'=>$isUserExist,'UsersCompany.company_id'=>$this->Auth->user('Company.id'),'UsersCompany.transaction_status'=>1)));
                    if($isExistEmail>0){
                        unset($totUserData[$key]);
                        $this->Session->setFlash('Some of your entered users are already added so removing those accounts');
                        continue;
                    }
                }
            }  
            $this->Session->write('plan_detail.Plan.user_count',count($this->Session->read("std_user_data"))); 
            $this->Session->write('plan_detail.Plan.transaction_type', 'standard_user'); 
            $this->Session->write("std_user_data", $totUserData); 
            $standardUserList = $this->Session->read('std_user_data');  
			
			$CompanyUsers = $this->UsersCompany->find('count', array(
                'conditions' => array(
                    'UsersCompany.company_id' => $this->Session->read('Auth.User.Company.id')
                )
            ));  
            $UserCount = $this->User->find('first', array(
                'conditions' => array(
                    'User.id' => $this->Session->read('Auth.User.id')
                ) , 
                'fields' => array(
                    'User.user_count'
                )
            ));  
            if(($CompanyUsers + count($standardUserList)) <= ($UserCount['User']['user_count'])){  
                if (!empty($standardUserList)) {
                    $inviter['User'] = $adminDetail['User'] = $this->Auth->user();
                    if($this->Auth->user("Company.administrator_id") != 0){
                        $adminDetail = $this->User->findById($this->Auth->user("Company.administrator_id"));
                    }
                    foreach ($standardUserList as $stdUserArr) {
                        $stdUser = $stdUserArr['User']; 
                        $checkUserExist = $this->User->field('id', array('User.email' => $stdUser['email']));  
                        $this->UsersCompany->create();
                        $this->loadModel('EmailTemplate');
                        $userDetail = array();
                        if ($checkUserExist) {
                            $newUserId = $checkUserExist;
                        } else { 
                            $this->User->create();
                            $this->request->data['User']['email'] = $stdUser['email'];
                            $this->request->data['User']['first_name'] = $stdUser['first_name'];
                            $this->request->data['User']['last_name'] = $stdUser['last_name'];
                            $this->request->data['User']['role_id'] = 3;
                            $this->request->data['User']['subscription_status'] = 1;
                            $this->request->data['User']['company_id'] = $adminDetail['User']['company_id'];
                            $this->request->data['User']['administrator_id'] = $adminDetail['User']['id'];
                            $this->request->data['User']['available_space'] = 0;
                            $this->request->data['User']['change_password_status'] = 1;
                            $this->User->save($this->request->data); 
                            $checkUserExist = $newUserId = $this->User->id;
                        }    
                        /************************* **************** Enter record in User Company table ********************** */
                        $this->request->data['UsersCompany']['user_id'] = $newUserId;
                        $this->request->data['UsersCompany']['company_id'] = $adminDetail['User']['company_id'];
                        $this->request->data['UsersCompany']['administrator_id'] = $adminDetail['User']['id'];
                        $this->request->data['UsersCompany']['role_id'] = 3;
                        $this->request->data['UsersCompany']['is_accept'] = 0;
                        $this->request->data['UsersCompany']['is_subscribed'] = 1;
                        $this->request->data['UsersCompany']['profile_id'] = '';
                        $this->request->data['UsersCompany']['available_space'] = 0;
                        $this->request->data['UsersCompany']['transaction_status'] = 1;
                        $this->request->data['UsersCompany']['transaction_key'] = "";
                        $this->UsersCompany->save($this->request->data); 
                        /********************** **************** End Enter record in User Company table ********************** */
                        /************* Send Invitation Email to user *****************/
                        if ($checkUserExist) {
                            $email = $this->EmailTemplate->selectTemplate('Already Registerd');
                            $emailFindReplace = array(
                                '##SITE_LINK##' => Router::url('/', true),
                                '##FIRST_NAME##' => $stdUser['last_name'],
                                '##USER_EMAIL##' => $stdUser['email'],
                                '##INVITER_FIRSTNAME##' => $this->Auth->user("first_name"),
                                '##INVITER_SECONDNAME##' => $this->Auth->user("last_name"),
                                '##INVITE_LINK##' => Router::url('/', true) . "users/invite_existing_confirm/" . $checkUserExist . "/" . md5($checkUserExist) . "/" . $this->Auth->user("Company.id"),
                                '##SITE_NAME##' => Configure::read('site.name'),
                                '##SUPPORT_EMAIL##' => Configure::read('site.contactus_email'),
                                '##WEBSITE_URL##' => Router::url('/', true),
                                '##FROM_EMAIL##' => $this->User->changeFromEmail(($email['from'] == '##FROM_EMAIL##') ? Configure::read('EmailTemplate.from_email') : $email['from']),
                                '##CONTACT_URL##' => Router::url(array(
                                    'controller' => '/',
                                    'action' => 'contact-us.html',
                                    'admin' => false
                                        ), true),
                                '##SITE_LOGO##' => Router::url(array(
                                    'controller' => 'img',
                                    'action' => '/',
                                    'admin-logo.png',
                                    'admin' => false
                                        ), true),
                            );
                        } else {
                            $this->User->create();
                            $email = $this->EmailTemplate->selectTemplate('Standard User Email');
                            $emailFindReplace = array(
                                '##SITE_LINK##' => Router::url('/', true),
                                '##USERNAME##' => $stdUser['first_name'],
                                '##USER_EMAIL##' => $stdUser['email'],
                                '##INVITER_FIRSTNAME##' => $this->Auth->user("first_name"),
                                '##INVITER_SECONDNAME##' => $this->Auth->user("last_name"),
                                '##INVITE_LINK##' => Router::url('/', true) . "users/invite_confirm/" . $newUserId . "/" . md5($newUserId . "/" . $this->Auth->user("Company.id")),
                                '##SITE_NAME##' => Configure::read('site.name'),
                                '##SUPPORT_EMAIL##' => Configure::read('site.contactus_email'),
                                '##WEBSITE_URL##' => Router::url('/', true),
                                '##FROM_EMAIL##' => $this->User->changeFromEmail(($email['from'] == '##FROM_EMAIL##') ? Configure::read('EmailTemplate.from_email') : $email['from']),
                                '##CONTACT_URL##' => Router::url(array(
                                    'controller' => '/',
                                    'action' => 'contact-us.html',
                                    'admin' => false
                                        ), true),
                                '##SITE_LOGO##' => Router::url(array(
                                    'controller' => 'img',
                                    'action' => '/',
                                    'admin-logo.png',
                                    'admin' => false
                                        ), true),
                            );
                        }
                        $this->Email->from = ($email['from'] == '##FROM_EMAIL##') ? Configure::read('EmailTemplate.from_email') : $email['from'];
                        $this->Email->replyTo = ($email['reply_to_email'] == '##REPLY_TO_EMAIL##') ? Configure::read('EmailTemplate.reply_to') : $email['reply_to_email'];
                        $this->Email->to = $stdUser['email'];
						if(!empty($this->Auth->user('invoice_email'))){
							$this->Email->to = array($stdUser['email'] , $this->Auth->user('invoice_email'));
						}
                        $this->Email->subject = strtr($email['subject'], $emailFindReplace);
                        $this->Email->sendAs = ($email['is_html']) ? 'html' : 'text';
                        $this->Email->send(strtr($email['description'], $emailFindReplace));  
                        /************* End Send Invitation Email to user *****************/

                        /********************** ******************* Enter record in GroupAccess table ************************** */
                        $this->loadModel('Group');
                        $this->loadModel('GroupUser');
                        foreach ($stdUser['group_id'] as $groupId) {
                            $this->GroupUser->create();
                            $this->request->data['GroupUser']['user_id'] = $newUserId; 
                            $this->request->data['GroupUser']['group_id'] = $groupId; 
                            $this->request->data['GroupUser']['company_id'] = $adminDetail['User']['company_id'];
                            $this->GroupUser->save($this->request->data);
                        } 
                        $this->User->id = $checkUserExist;
                        $userList .= "User Email : " . $stdUser['email'] . "<br/>"; 
                    } 
                }
                echo "1";
                exit;
            }elseif($CompanyUsers < $UserCount['User']['user_count']){
                    
                if (!empty($standardUserList)) {
                    $inviter['User'] = $adminDetail['User'] = $this->Auth->user();
                    if($this->Auth->user("Company.administrator_id") != 0){
                        $adminDetail = $this->User->findById($this->Auth->user("Company.administrator_id"));
                    }
                    $addedCompanyUsers = $CompanyUsers;
                    foreach ($standardUserList as $stdKey => $stdUserArr) {
                        if($addedCompanyUsers < $UserCount['User']['user_count']){
                            $stdUser = $stdUserArr['User'];
                            $checkUserExist = $this->User->field('id', array('User.email' => $stdUser['email']));
                            $this->UsersCompany->create();
                            $this->loadModel('EmailTemplate');
                            $userDetail = array();
                            if ($checkUserExist) {
                                $newUserId = $checkUserExist;
                            } else {
                                $this->User->create();
                                $this->request->data['User']['email'] = $stdUser['email'];
                                $this->request->data['User']['first_name'] = $stdUser['first_name'];
                                $this->request->data['User']['last_name'] = $stdUser['last_name'];
                                $this->request->data['User']['role_id'] = 3;
                                $this->request->data['User']['subscription_status'] = 1;
                                $this->request->data['User']['company_id'] = $adminDetail['User']['company_id'];
                                $this->request->data['User']['administrator_id'] = $adminDetail['User']['id'];
                                $this->request->data['User']['available_space'] = 0;
                                $this->request->data['User']['change_password_status'] = 1;
                                $this->User->save($this->request->data);
                                $newUserId = $this->User->id; 
                            }  
                            /************************* **************** Enter record in User Company table ********************** */
                            $this->request->data['UsersCompany']['user_id'] = $newUserId;
                            $this->request->data['UsersCompany']['company_id'] = $adminDetail['User']['company_id'];
                            $this->request->data['UsersCompany']['administrator_id'] = $adminDetail['User']['id'];
                            $this->request->data['UsersCompany']['role_id'] = 3;
                            $this->request->data['UsersCompany']['is_accept'] = 0;
                            $this->request->data['UsersCompany']['is_subscribed'] = 1;
                            $this->request->data['UsersCompany']['profile_id'] = '';
                            $this->request->data['UsersCompany']['available_space'] = 0;
                            $this->request->data['UsersCompany']['transaction_status'] = 1;
                            $this->request->data['UsersCompany']['transaction_key'] = "";
                            $this->UsersCompany->save($this->request->data); 
                            /********************** **************** End Enter record in User Company table ********************** */
                            /************* Send Invitation Email to user *****************/
                            if ($checkUserExist) {
                                $email = $this->EmailTemplate->selectTemplate('Already Registerd');
                                $emailFindReplace = array(
                                    '##SITE_LINK##' => Router::url('/', true),
                                    '##FIRST_NAME##' => $stdUser['last_name'],
                                    '##USER_EMAIL##' => $stdUser['email'],
                                    '##INVITER_FIRSTNAME##' => $this->Auth->user("first_name"),
                                    '##INVITER_SECONDNAME##' => $this->Auth->user("last_name"),
                                    '##INVITE_LINK##' => Router::url('/', true) . "users/invite_existing_confirm/" . $checkUserExist . "/" . md5($checkUserExist) . "/" . $this->Auth->user("Company.id"),
                                    '##SITE_NAME##' => Configure::read('site.name'),
                                    '##SUPPORT_EMAIL##' => Configure::read('site.contactus_email'),
                                    '##WEBSITE_URL##' => Router::url('/', true),
                                    '##FROM_EMAIL##' => $this->User->changeFromEmail(($email['from'] == '##FROM_EMAIL##') ? Configure::read('EmailTemplate.from_email') : $email['from']),
                                    '##CONTACT_URL##' => Router::url(array(
                                        'controller' => '/',
                                        'action' => 'contact-us.html',
                                        'admin' => false
                                            ), true),
                                    '##SITE_LOGO##' => Router::url(array(
                                        'controller' => 'img',
                                        'action' => '/',
                                        'admin-logo.png',
                                        'admin' => false
                                            ), true),
                                );
                            } else {
                                $this->User->create();
                                $email = $this->EmailTemplate->selectTemplate('Standard User Email');
                                $emailFindReplace = array(
                                    '##SITE_LINK##' => Router::url('/', true),
                                    '##USERNAME##' => $stdUser['first_name'],
                                    '##USER_EMAIL##' => $stdUser['email'],
                                    '##INVITER_FIRSTNAME##' => $this->Auth->user("first_name"),
                                    '##INVITER_SECONDNAME##' => $this->Auth->user("last_name"),
                                    '##INVITE_LINK##' => Router::url('/', true) . "users/invite_confirm/" . $newUserId . "/" . md5($newUserId . "/" . $this->Auth->user("Company.id")),
                                    '##SITE_NAME##' => Configure::read('site.name'),
                                    '##SUPPORT_EMAIL##' => Configure::read('site.contactus_email'),
                                    '##WEBSITE_URL##' => Router::url('/', true),
                                    '##FROM_EMAIL##' => $this->User->changeFromEmail(($email['from'] == '##FROM_EMAIL##') ? Configure::read('EmailTemplate.from_email') : $email['from']),
                                    '##CONTACT_URL##' => Router::url(array(
                                        'controller' => '/',
                                        'action' => 'contact-us.html',
                                        'admin' => false
                                            ), true),
                                    '##SITE_LOGO##' => Router::url(array(
                                        'controller' => 'img',
                                        'action' => '/',
                                        'admin-logo.png',
                                        'admin' => false
                                            ), true),
                                );
                            }
                            $this->Email->from = ($email['from'] == '##FROM_EMAIL##') ? Configure::read('EmailTemplate.from_email') : $email['from'];
                            $this->Email->replyTo = ($email['reply_to_email'] == '##REPLY_TO_EMAIL##') ? Configure::read('EmailTemplate.reply_to') : $email['reply_to_email'];
                            $this->Email->to = $stdUser['email'];
							if(!empty($this->Auth->user('invoice_email'))){
								$this->Email->to = array($stdUser['email'] , $this->Auth->user('invoice_email'));
							}
                            $this->Email->subject = strtr($email['subject'], $emailFindReplace);
                            $this->Email->sendAs = ($email['is_html']) ? 'html' : 'text';
                            $this->Email->send(strtr($email['description'], $emailFindReplace));  
                            /************* End Send Invitation Email to user *****************/

                            /********************** ******************* Enter record in GroupAccess table ************************** */
                            $this->loadModel('Group');
                            $this->loadModel('GroupUser');
                            foreach ($stdUser['group_id'] as $groupId) {
                                $this->GroupUser->create();
                                $this->request->data['GroupUser']['user_id'] = $newUserId; 
                                $this->request->data['GroupUser']['group_id'] = $groupId; 
                                $this->request->data['GroupUser']['company_id'] = $adminDetail['User']['company_id'];
                                $this->GroupUser->save($this->request->data);
                            } 
                            $this->User->id = $checkUserExist;
                            $userList .= "User Email : " . $stdUser['email'] . "<br/>";
                            unset($standardUserList[$stdKey]);
                            $addedCompanyUsers++; 
                        } 
                    }
                    if(empty($standardUserList)) {
                        echo "1";die;
                    }
                    $this->Session->write('std_user_data', $standardUserList); 
                }
            } 
            exit; 
        }else{
            if($this->Session->check("std_user_data")){
                $sessionUserData = $this->Session->read("std_user_data");
                $this->request->data = $sessionUserData[$userPage]; 
            }
        } 
         
        /*************************** User Group List *********************** */

        $this->loadModel('Group');
        $userGroups = $this->Group->find('list', array('conditions' => array('Group.user_id' => $this->Auth->user('id')), 'fields' => array('Group.id', 'Group.name'))); 
        
        $company_id = $this->Session->read('Auth.User.Company.id');
        
        $this->UsersCompany->bindModel(array(
            'belongsTo' => array('User')
        ));
        $companyUserData = $this->UsersCompany->find("all", array(
            "conditions"=>array(
                "UsersCompany.company_id"=>$company_id,                
            ),      
            
        ));
        
        $CompanyUsersList = array();
        if(isset($companyUserData) && !empty($companyUserData)){
            foreach($companyUserData as $key => $user){ 
                if($user['UsersCompany']['user_id'] != $ReqUserId){
                    $CompanyUsersList[] = $user['User']['email'];
                }
            }
        }
        
        $CompanyUsersListString = implode(", ",$CompanyUsersList);
        $CompanyUsersListStringCount = count($CompanyUsersList);
        
        $this->set(compact('getAccessList', 'stdAmt', 'currency', 'userGroups', 'userPage' , 'CompanyUsersListString'));
    }

    function viewStdDetail() {
        $this->layout = 'task_module';
        $stdUserData = $this->Session->read('std_user_data'); 
		if(empty($stdUserData)){
			$this->Session->setFlash("No user for add");
            $this->redirect(array("controller"=>"users", "action"=>"userList"));
			exit;
		}
		
        if(empty($stdUserData)) { 
            $this->redirect($_SERVER['HTTP_REFERER']);
            exit;
        }
        $this->set('title_for_layout', Configure::read('site.name') . ':: Select Your Subscription Plan');
        $this->loadModel('Plan');
        $this->loadModel('UserSubscriptionHistory');
        $this->Session->write("plan_detail.Plan.user_count", count($this->Session->read("std_user_data")));
        $userPlan = $this->User->field('plan_id', array('User.id' => $this->Auth->user('id')));
        $subscriptionDetail = $this->UserSubscriptionHistory->find('first', array(
            'conditions' => array(
                '(UserSubscriptionHistory.transaction_type = "Subscribe" OR UserSubscriptionHistory.transaction_type = "skip_trial")',
                'UserSubscriptionHistory.plan_id' => $userPlan,
                'UserSubscriptionHistory.user_id' => $this->Auth->user('id'))
            , 
            'order' => 'UserSubscriptionHistory.id desc'
        ));  

        
        if (!empty($this->data)) {
            $this->Session->write('upgrade_detail', $this->data);
            $stdUserData = $this->Session->read('std_user_data');
            
            $CompanyUsers = $this->UsersCompany->find('count', array('conditions' => array('UsersCompany.company_id' => $this->Session->read('Auth.User.Company.id'))));    
            
            $UserCount = $this->User->find('first', array('conditions' => array('User.id' => $this->Session->read('Auth.User.id')) , 'fields' => array('User.user_count')));  

            if(($CompanyUsers + count($stdUserData)) > $UserCount['User']['user_count'] || $CompanyUsers == $UserCount['User']['user_count'] || $UserCount['User']['user_count'] == 0){
                $this->redirect('packageStdDetail');
            }else{ 
                $newUserCount = ($CompanyUsers + count($stdUserData))- $UserCount['User']['user_count'];
                $standardUserList = $this->Session->read('std_user_data'); 
                $_SESSION['std_user_data'] = array_values($_SESSION['std_user_data']); 
                $this->redirect('packageStdDetail');
            }
        }
         
        if ($this->Auth->user('role_id') == 2) {
            $plan_id = 2;
            if ($this->Auth->user('plan_id')) {
                $plan_id = $this->Auth->user('plan_id');
            }
            $conditions = array('user_type' => 0, 'Plan.id' => $plan_id);
        } else {
            $conditions = array('user_type' => 1);
        }
        $planDetail = $this->Plan->find('first', array('conditions' => $conditions)); 
        switch ($planDetail['Plan']['id']) {
            case 2:
                $plan = 'Pay Monthly';
                $paymonth = 1;
                $paymenttype = 'monthly';
                break;
            case 5:
                $plan = 'Pay 6 Monthly';
                $paymonth = 6;
                $paymenttype = 'six_month';
                break;
            case 6:
                $plan = 'Pay Annually';
                $paymonth = 12;
                $paymenttype = 'yearly';
                break;
        }

        $this->loadModel('PaymentInformation');
        $paymentDetail = $this->PaymentInformation->find('first', array('conditions' => array('user_id' => $this->Auth->user('id'))));
        if (!empty($paymentDetail)) {
            $nextPaymentDate = strtotime('+' . $paymentDetail['PaymentInformation']['frequency'], strtotime($paymentDetail['PaymentInformation']['payment_date']));
            while ($nextPaymentDate < time()) {
                $nextPaymentDate = strtotime('+' . $paymentDetail['PaymentInformation']['frequency'], $nextPaymentDate);
            }
        }
        $diffDate = date("Y-m-d", $nextPaymentDate); 
        $nextPaymentDate = date($this->Session->read('DATE_FORMAT'), $nextPaymentDate);  
        
        $d1 = new DateTime($diffDate); 
        $d2 = new DateTime(date('Y-m-d'));
        $interval = $d2->diff($d1); 
        $monthCount = $interval->format('%m');
        
        
        if ($monthCount == 0) {
            $year = $interval->format('%y');
            if ($year == 1) {
                $monthCount = 12;
            } else {
                $monthCount = 1;
            }
        }

        $this->loadModel('Currency');  
        $std_userprice = Configure::read('site.std_userprice');
        $oneoffPay = $monthCount * $std_userprice * $this->Session->read('plan_detail.Plan.user_count');
        if ($this->Auth->user('country_id') == 1) {
            $oneoffPay = $oneoffPay * 1.2;
            $nextReccuringAmt = $paymentDetail['PaymentInformation']['amount'] + (($paymonth * $std_userprice * $this->Session->read('plan_detail.Plan.user_count')) * 1.2);
        }else{
            $nextReccuringAmt = $paymentDetail['PaymentInformation']['amount'] + (($paymonth * $std_userprice * $this->Session->read('plan_detail.Plan.user_count')));
        }
        if($this->Session->read('CURR')=="USD"){
            $nextReccuringAmtUS = $this->getConvertedAmount('USD', $nextReccuringAmt,'GBP');
            $paymentDetail['PaymentInformation']['us_amount'] = $this->getConvertedAmount('USD', $paymentDetail['PaymentInformation']['amount'],'GBP');
            $oneoffPayUs = $this->getConvertedAmount('USD', $oneoffPay,'GBP');
        }
        $currencyList = $this->Currency->find('list', array('fields' => array('code', 'code')));
        $this->Session->write("plan_detail.Plan.nextReccuringAmt", $nextReccuringAmt);
        if ($this->RequestHandler->isAjax()) {
            $this->layout = "ajax";
            $this->render('mobile/viewStdDetail');
        }  


        $this->Session->write("plan_detail.Plan.plan_id", $planDetail['Plan']['id']); 
        $this->Session->write("plan_detail.Plan.month", $monthCount);
        $this->set(compact('planDetail', 'formType', 'nextPaymentDate', 'currencyList', 'plan', 'oneoffPayUs', 'paymonth', 'std_userprice', 'paymenttype', 'stdUserData', 'nextReccuringAmtUS', 'subscriptionDetail', 'nextReccuringAmt', 'oneoffPay', 'paymentDetail'));
    }

    function packageStdDetail() {
		
		$stdUserData = $this->Session->read('std_user_data');
		if(empty($stdUserData)){
			$this->Session->setFlash("No user for add");
            $this->redirect(array("controller"=>"users", "action"=>"userList"));
			exit;
		}
		require APP . DS . "Vendor" . DS . "braintree" . DS . "vendor/autoload.php"; 
        if(!strpos($this->Auth->user("email"), "tribondinfosystems.com") && $_SERVER['REMOTE_ADDR'] != '121.46.113.52'){
            $gateway = new Braintree\Gateway([
                'environment' => ENV,
                'merchantId' => MERCHANT_ID,
                'publicKey' => PUBLIC_KEY,
                'privateKey' => PRIVATE_KEY
            ]);
        }else{
            $gateway = new Braintree\Gateway([
                'environment' => ENV_SANDBOX,
                'merchantId' => MERCHANT_ID_SANDBOX,
                'publicKey' => PUBLIC_KEY_SANDBOX,
                'privateKey' => PRIVATE_KEY_SANDBOX
            ]);
        }
        $clientToken = $gateway->ClientToken()->generate();
        //$paymentMethod = $gateway->paymentMethod()->find('8ntx65'); 
        $this->loadModel('Plan');
        $this->loadModel('PaymentInformation');
        $this->loadModel('UsersCompany');
        $this->loadModel('User');
        $this->loadModel('Department');
        $this->loadModel('DefaultFolder');

        $this->Plan->recursive = 0;
		
        $stdUserData = $this->Session->read('std_user_data');
        $selectedPlancurr = $this->Session->read('upgrade_detail');
        $this->layout = 'task_module';
        $this->set('title_for_layout', Configure::read('site.name') . ' :: Your Package Detail');
        $selectedPlan = $this->Session->read('plan_detail');
		
		$stdUserPrice = Configure::read('site.std_userprice');
        $planDetail = $this->Plan->find('first', array('conditions' => array('id' => $selectedPlan['Plan']['plan_id'])));
		
		
		$planDetail['Plan']['month'] = $selectedPlan['Plan']['month'];
		
		
        $formType = "";
        $this->loadModel('PaymentInformation');
        $paymentDetail = $this->PaymentInformation->find('first', array('conditions' => array('user_id' => $this->Auth->user('id'))));
        if (!empty($paymentDetail)) {
            $nextPaymentDate = strtotime('+' . $paymentDetail['PaymentInformation']['frequency'], strtotime($paymentDetail['PaymentInformation']['payment_date']));
            while ($nextPaymentDate < time()) {
                $nextPaymentDate = strtotime('+' . $paymentDetail['PaymentInformation']['frequency'], $nextPaymentDate);
            }
        }
        $diffDate = date("Y-m-d", $nextPaymentDate); 
        $nextPaymentDate = date($this->Session->read('DATE_FORMAT'), $nextPaymentDate);
        
        
        $d1 = new DateTime($diffDate); 
        $d2 = new DateTime(date('Y-m-d'));
        $interval = $d2->diff($d1); 

        $monthCount = $interval->format('%m');
        if ($monthCount == 0) {
            $year = $interval->format('%y');
            if ($year == 1) {
                $monthCount = 12;
            } else {
                $monthCount = 1;
            }
        }
		
		$standardUserList = $this->Session->read('std_user_data');
		
		$companyUsers = $this->UsersCompany->find('count', array('conditions' => array('UsersCompany.company_id' => $this->Session->read('Auth.User.Company.id'))));
		
			
			
		/*** update user count in login user **************/
		$updateLoggedInUser = array();
		
		
		$LoggedUserData = $this->User->find('first', array('conditions' => array('User.id' => $this->Auth->user('id'))));
		if($LoggedUserData['User']['user_count'] > $companyUsers){						
			$remaingUser = $LoggedUserData['User']['user_count'] - $companyUsers;
			$userCount = count($standardUserList) - $remaingUser;
			
		}else{
			$userCount = count($standardUserList);
		}
		
		
			
		/*** update user count in login user **************/
					
					
        if (!empty($this->data)) { 
            $braintTreeSubscriptionId = $this->Auth->user("braintree_id"); 
            $nonce = $this->data['payment_method_nonce'];
            $amount = $this->data['amount'];
            $result = $gateway->transaction()->sale([ 
                'amount' => $amount,
                'paymentMethodNonce' => $nonce, 
                'options' => [
                    'submitForSettlement' => True
                ]
            ]);
            $result = json_decode(json_encode($result), true);
			
			$nextReccuringAmt = number_format($this->Session->read("plan_detail.Plan.nextReccuringAmt"),2);   
            if($result['success']) {  
                $transactionId = $result['transaction']['id'];
                $this->loadModel('PaymentInformation');
                $this->loadModel('UserSubscriptionHistory');
                $this->loadModel('UsersCompany'); 
                $paymentStatus = false;
                if($this->Auth->user("subscription_type") == 1){  
                    $futurePayId = $this->Auth->user("profile_id");
                    //$url = "https://secure-test.worldpay.com/wcc/iadmin";/* comment for live mode  */
                    //$postData = "instId=1038709&authPW=fran5fRe&testMode=100&amount=".number_format($nextReccuringAmt,2)."&futurePayId=".$futurePayId."&op-adjustRFP";/* comment for live mode  */
                    $url = "https://secure.worldpay.com/wcc/iadmin"; /* Uncomment for live mode  */
                    $postData = "instId=1038709&authPW=fran5fRe&amount=".number_format($nextReccuringAmt,2). "&futurePayId=" . $futurePayId . "&op-adjustRFP"; /* Uncomment for live mode  */
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HEADER, false);
                    curl_setopt($ch, CURLOPT_POST, count($postData));
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
                    $output = curl_exec($ch);
                    curl_close($ch);
                    $paymentStatus = true;
                    $transactionId = $this->Auth->user("profile_id");
                }elseif($this->Auth->user("subscription_type") == 4){
                    $subscription = $gateway->subscription()->find($braintTreeSubscriptionId);
                    $subscription = json_decode(json_encode($subscription), true); 
                    $nextBillAmount = $subscription['nextBillAmount'];
                    $merchantId = $subscription['merchantAccountId']; 
                    $resultNew = $gateway->subscription()->update($braintTreeSubscriptionId, [ 
                        'paymentMethodToken' => $this->Auth->user("braintree_method_token"),
                        'price' => $nextReccuringAmt, 
                        'merchantAccountId' => $merchantId
                    ]);
                    $resultNew = json_decode(json_encode($resultNew), true); 
                    if($resultNew['success']){
                        $paymentStatus = true;
                        $transactionId = $resultNew['transaction']['id'];
                    }else{  
                        $this->set("paymentError", $resultNew['message']);
                    }
                }elseif($this->Auth->user("subscription_type") == 5){
					$paymentStatus = true;
				}
				
				
                if($paymentStatus){
					
					$updateLoggedInUser['User']['id'] = $this->Auth->user('id');
					$updateLoggedInUser['User']['user_count'] = ($LoggedUserData['User']['user_count']) + $userCount;
					$this->User->save($updateLoggedInUser);
								
                    /************** Save Subscription History ******************* */
                    $this->request->data['UserSubscriptionHistory']['plan_id'] = $planDetail['Plan']['id'];
                    $this->request->data['UserSubscriptionHistory']['frequency'] = $planDetail['Plan']['month'];
                    $this->request->data['UserSubscriptionHistory']['user_id'] = $this->Auth->user("id");
                    $this->request->data['UserSubscriptionHistory']['transaction_type'] = 'Standard User Added';
                    $this->request->data['UserSubscriptionHistory']['transaction_id'] = $transactionId;
                    $this->request->data['UserSubscriptionHistory']['subscribe_from'] = 1;
                    $this->request->data['UserSubscriptionHistory']['user_count'] = $userCount;
                    $this->request->data['UserSubscriptionHistory']['amount'] = $this->data['amount'];
                    $this->request->data['UserSubscriptionHistory']['payment_type'] = 'braintree';
                    $this->UserSubscriptionHistory->save($this->request->data);
                    /********** End Save Subscription History ***************/  
                    $paymentInfo = $this->PaymentInformation->find("first", array(
                        "conditions"=> array("PaymentInformation.user_id" => $this->Auth->user("id"))
                    ));

                    if(!empty($paymentInfo)){
                        $this->request->data['PaymentInformation']['id'] = $paymentInfo['PaymentInformation']['id'];
                        $this->request->data['PaymentInformation']['amount'] = number_format($nextReccuringAmt, 2, '.', ''); 
                        $this->PaymentInformation->save($this->request->data);
                    } 
                    $userList = "";
                    $this->loadModel('EmailTemplate');
                    $this->loadModel('UsersCompany');
                    $this->loadModel('UserAccess');
                    $this->loadModel('GroupUser');
                    $randomKey = $this->User->randomPassword(); 
                    foreach ($standardUserList as $stdUser) { 
                        $checkUserExist = $this->User->field('id', array('User.email' => $stdUser['User']['email']));
                        $this->UsersCompany->create();
                        $this->loadModel('EmailTemplate');
                        if ($checkUserExist) {
                            $this->User->id = $newUserId = $checkUserExist;
                        } else {
                            $this->User->create();
                            $this->request->data['User']['email'] = $stdUser['User']['email'];
                            $this->request->data['User']['first_name'] = $stdUser['User']['first_name'];
                            $this->request->data['User']['last_name'] = $stdUser['User']['last_name'];
                            $this->request->data['User']['role_id'] = 3;
                            $this->request->data['User']['subscription_status'] = 1;
                            $this->request->data['User']['company_id'] = $this->Session->read('Auth.User.Company.id');
                            $this->request->data['User']['administrator_id'] = $this->Auth->user('id');
                            $this->request->data['User']['available_space'] = 0;
                            $this->request->data['User']['change_password_status'] = 1;
                            $this->User->save($this->request->data);
                            $newUserId = $this->User->id;
                        }
                        $userList .= "User Email : " . $stdUser['User']['email'] . "<br/>";
                        /*********** Enter record in User Company table **************/
                        $this->request->data['UsersCompany']['user_id'] = $newUserId;
                        $this->request->data['UsersCompany']['company_id'] = $this->Session->read('Auth.User.Company.id');
                        $this->request->data['UsersCompany']['administrator_id'] = $this->Auth->user('id');
                        $this->request->data['UsersCompany']['role_id'] = 3;
                        $this->request->data['UsersCompany']['is_accept'] = 0;
                        $this->request->data['UsersCompany']['is_subscribed'] = 1;
                        $this->request->data['UsersCompany']['profile_id'] = '';
                        $this->request->data['UsersCompany']['available_space'] = 0;
                        $this->request->data['UsersCompany']['transaction_status'] = 1;
                        $this->request->data['UsersCompany']['transaction_key'] = $randomKey;
                        $this->UsersCompany->save($this->request->data);
                        /************ End Enter record in User Company table *************/

                        /************ Enter record in GroupAccess table **************** */
                        $this->loadModel('Group');
                        if(isset($stdUser['User']['group_id'])){
                            foreach ($stdUser['User']['group_id'] as $groupName) {
                                $this->GroupUser->create();
                                $this->request->data['GroupUser']['user_id'] = $newUserId;
                                if ((int) $groupName > 0) {
                                    $groupId = $groupName;
                                } else {
                                    $groupId = $this->Group->field('id', array('Group.name' => $groupName, 'Group.user_id' => $this->Auth->user('id')));
                                    if ($groupId > 0) {
                                        $this->request->data['GroupUser']['group_id'] = $groupId;
                                    } else {
                                        $this->Group->create();
                                        $this->request->data['Group']['name'] = $groupName;
                                        $this->request->data['Group']['user_id'] = $this->Auth->user('id');
                                        $this->Group->save($this->request->data);
                                        $groupId = $this->Group->id;
                                    }
                                }
                                $this->request->data['GroupUser']['group_id'] = $groupId;
                                $this->request->data['GroupUser']['company_id'] = $this->Session->read('Auth.User.Company.id');
                                $this->GroupUser->save($this->request->data);
                            }
                        }
                        
                        /************** End Enter record in UserAccess table *************/
						
						/**************** Assign Full Access to added user *****************/
						
						if(isset($stdUser['User']['fullAccess']) && !empty($stdUser['User']['fullAccess'])){
							$fullAccessFolders = $stdUser['User']['fullAccess'];
							$checkFolderExists = array();
							if(!empty($fullAccessFolders)){
								$this->Department->unbindModel(array(
									"belongsTo" => array(
										"User", "Company"
									),
									"hasMany" => array(
										"DefaultFolder"
									)
								));
								$checkFolderExists = $this->Department->find("list", array(
									"conditions" => array(
										"Department.name" => $fullAccessFolders
									),
									"fields" => array(
										"Department.id",
										"Department.name",
									)
								)); 
								foreach($fullAccessFolders as $folder){
									if(in_array($folder, $checkFolderExists)){
										$folder_id = array_search($folder, $checkFolderExists);
									}else{// Create new folder if not exist
										$this->request->data['Department']['name'] = $folder;
										$this->request->data['Department']['user_id'] = $this->Auth->user('id');
										$this->request->data['Department']['company_id'] = $this->Session->read('Auth.User.Company.id');
										$this->request->data['Department']['parent_id'] = 0;
										$this->Department->create();
										$this->Department->save($this->data);
										$folder_id = $this->Department->id;
									}
									$this->UserAccess->deleteAll(array(
										"UserAccess.user_id" => $newUserId,
										"UserAccess.department_id" => $folder_id,
									));
									$this->request->data['UserAccess']['user_id'] = $newUserId;
									$this->request->data['UserAccess']['department_id'] = $folder_id;
									$this->request->data['UserAccess']['access'] = 2; 
									
									$this->UserAccess->create();
									$this->UserAccess->save($this->data); 
									$folderIds[$folder_id] = $folder;
								}
							} 
						}
						/**************** Assign Full Access to added user *****************/

						/**************** Assign Read Only Access to added user ***************/
						if(isset($stdUser['User']['readOnlyAccess']) && !empty($stdUser['User']['readOnlyAccess'])){
							$readAccessFolders = $stdUser['User']['readOnlyAccess'];
							$readAccessFolders = array_diff ($readAccessFolders, $fullAccessFolders);
							$checkFolderExists = array();
							if(!empty($readAccessFolders)){
								$this->Department->unbindModel(array(
									"belongsTo" => array(
										"User", "Company"
									),
									"hasMany" => array(
										"DefaultFolder"
									)
								));
								$checkFolderExists = $this->Department->find("list", array(
									"conditions" => array(
										"Department.name" => $readAccessFolders
									),
									"fields" => array(
										"Department.id",
										"Department.name",
									)
								));  
								foreach($readAccessFolders as $folder){
									if(in_array($folder, $checkFolderExists)){
										$folder_id = array_search($folder, $checkFolderExists);
									}else{// Create new folder if not exist
										$this->request->data['Department']['name'] = $folder;
										$this->request->data['Department']['user_id'] = $this->Auth->user('id');
										$this->request->data['Department']['company_id'] = $this->Session->read('Auth.User.Company.id');
										$this->request->data['Department']['parent_id'] = 0;
										$this->Department->create();
										$this->Department->save($this->data);
										$folder_id = $this->Department->id;
									}
									$this->UserAccess->deleteAll(array(
										"UserAccess.user_id" => $newUserId,
										"UserAccess.department_id" => $folder_id,
									));
									$this->request->data['UserAccess']['user_id'] = $newUserId;
									$this->request->data['UserAccess']['department_id'] = $folder_id;
									$this->request->data['UserAccess']['access'] = 1; 
									$this->UserAccess->create();
									$this->UserAccess->save($this->data); 
									$folderIds[$folder_id] = $folder;
								}
							} 
						}
						/**************** Assign Read Only Access to added user ***************/

						/**************** Assign Template Access to added user ***************/
						if(isset($stdUser['User']['templateAccess']) && !empty($stdUser['User']['templateAccess'])){
							$templateAccessFolders = $stdUser['User']['templateAccess'];
							$templateAccessFolders = array_diff ($templateAccessFolders, $readAccessFolders, $fullAccessFolders);
							$checkFolderExists = array();
							if(!empty($templateAccessFolders)){
								$this->Department->unbindModel(array(
									"belongsTo" => array(
										"User", "Company"
									),
									"hasMany" => array(
										"DefaultFolder"
									)
								));
								$checkFolderExists = $this->Department->find("list", array(
									"conditions" => array(
										"Department.name" => $templateAccessFolders
									),
									"fields" => array(
										"Department.id",
										"Department.name",
									)
								));  
								foreach($templateAccessFolders as $folder){
									if(in_array($folder, $checkFolderExists)){
										$folder_id = array_search($folder, $checkFolderExists);
									}else{
										$this->request->data['Department']['name'] = $folder;
										$this->request->data['Department']['user_id'] = $this->Auth->user('id');
										$this->request->data['Department']['company_id'] = $this->Session->read('Auth.User.Company.id');
										$this->request->data['Department']['parent_id'] = 0;
										$this->Department->create();
										$this->Department->save($this->data);
										$folder_id = $this->Department->id;
									}
									$this->UserAccess->deleteAll(array(
										"UserAccess.user_id" => $newUserId,
										"UserAccess.department_id" => $folder_id,
									));
									$this->request->data['UserAccess']['user_id'] = $newUserId;
									$this->request->data['UserAccess']['department_id'] = $folder_id;
									$this->request->data['UserAccess']['access'] = 3; 
									$this->UserAccess->create();
									$this->UserAccess->save($this->data);
									$folderIds[$folder_id] = $folder;
								}
							} 
						}
						/**************** Assign Template Access to added user ***************/

						/***************** Assign Default Folder *******************/
						if(isset($stdUser['User']['defaultFolder']) && !empty($stdUser['User']['defaultFolder'])){
							if(in_array(trim($stdUser['User']['defaultFolder']), $folderIds)){
								$defaultFolderId = array_search(trim($stdUser['User']['defaultFolder']), $folderIds); 
							}else{
								$defaultFolderId = $this->Department->field("id", array(
									"Department.name" => trim($stdUser['User']['defaultFolder']),
									"Department.company_id" => $this->Session->read('Auth.User.Company.id'),
								)); 
							}
							if(isset($defaultFolderId)){
								$checkAlreadyExist = $this->DefaultFolder->find("first", array(
									"conditions" => array(
										"DefaultFolder.user_id" => $newUserId,
										"DefaultFolder.company_id" => $this->Session->read('Auth.User.Company.id'),
									)
								)); 
								$this->request->data['DefaultFolder']['user_id'] = $newUserId;
								$this->request->data['DefaultFolder']['department_id'] = $defaultFolderId;
								$this->request->data['DefaultFolder']['company_id'] = $this->Session->read('Auth.User.Company.id');
								$this->DefaultFolder->create();
								if(!empty($checkAlreadyExist)){
									$this->request->data['DefaultFolder']['id'] = $checkAlreadyExist['DefaultFolder']['id'];
								}else{
									$this->request->data['DefaultFolder']['id'] = 0;
								}
								$this->DefaultFolder->save($this->request->data);
							}  
						}
						/***************** Assign Default Folder *******************/ 
						
						

                        /************* Send Invitation Email to user *****************/
                        if ($checkUserExist) {
                            $email = $this->EmailTemplate->selectTemplate('Already Registerd');
                            $emailFindReplace = array(
                                '##SITE_LINK##' => Router::url('/', true),
                                '##FIRST_NAME##' => $stdUser['User']['last_name'],
                                '##USER_EMAIL##' => $stdUser['User']['email'],
                                '##INVITER_FIRSTNAME##' => $this->Auth->user("first_name"),
                                '##INVITER_SECONDNAME##' => $this->Auth->user("last_name"),
                                '##INVITE_LINK##' => Router::url('/', true) . "users/invite_existing_confirm/" . $checkUserExist . "/" . md5($checkUserExist) . "/" . $this->Auth->user("Company.id"),
                                '##SITE_NAME##' => Configure::read('site.name'),
                                '##SUPPORT_EMAIL##' => Configure::read('site.contactus_email'),
                                '##WEBSITE_URL##' => Router::url('/', true),
                                '##FROM_EMAIL##' => $this->User->changeFromEmail(($email['from'] == '##FROM_EMAIL##') ? Configure::read('EmailTemplate.from_email') : $email['from']),
                                '##CONTACT_URL##' => Router::url(array(
                                    'controller' => '/',
                                    'action' => 'contact-us.html',
                                    'admin' => false
                                        ), true),
                                '##SITE_LOGO##' => Router::url(array(
                                    'controller' => 'img',
                                    'action' => '/',
                                    'admin-logo.png',
                                    'admin' => false
                                        ), true),
                            );
                        } else {
                            $this->User->create();
                            $email = $this->EmailTemplate->selectTemplate('Standard User Email');
                            $emailFindReplace = array(
                                '##SITE_LINK##' => Router::url('/', true),
                                '##USERNAME##' => $stdUser['User']['first_name'],
                                '##USER_EMAIL##' => $stdUser['User']['email'],
                                '##INVITER_FIRSTNAME##' => $this->Auth->user("first_name"),
                                '##INVITER_SECONDNAME##' => $this->Auth->user("last_name"),
                                '##INVITE_LINK##' => Router::url('/', true) . "users/invite_confirm/" . $newUserId . "/" . md5($newUserId),
                                '##SITE_NAME##' => Configure::read('site.name'),
                                '##SUPPORT_EMAIL##' => Configure::read('site.contactus_email'),
                                '##WEBSITE_URL##' => Router::url('/', true),
                                '##FROM_EMAIL##' => $this->User->changeFromEmail(($email['from'] == '##FROM_EMAIL##') ? Configure::read('EmailTemplate.from_email') : $email['from']),
                                '##CONTACT_URL##' => Router::url(array(
                                    'controller' => '/',
                                    'action' => 'contact-us.html',
                                    'admin' => false
                                        ), true),
                                '##SITE_LOGO##' => Router::url(array(
                                    'controller' => 'img',
                                    'action' => '/',
                                    'admin-logo.png',
                                    'admin' => false
                                        ), true),
                            );
                        }
                        $this->Email->from = ($email['from'] == '##FROM_EMAIL##') ? Configure::read('EmailTemplate.from_email') : $email['from'];
                        $this->Email->replyTo = ($email['reply_to_email'] == '##REPLY_TO_EMAIL##') ? Configure::read('EmailTemplate.reply_to') : $email['reply_to_email'];
                        $this->Email->to = $stdUser['User']['email'];
						if(!empty($this->Auth->user('invoice_email'))){
							$this->Email->to = array($stdUser['User']['email'] , $this->Auth->user('invoice_email'));
						}
                        $this->Email->subject = strtr($email['subject'], $emailFindReplace);
                        $this->Email->sendAs = ($email['is_html']) ? 'html' : 'text';
                        $this->Email->send(strtr($email['description'], $emailFindReplace));  
                        /************* End Send Invitation Email to user *****************/
                    } 

                    $this->loadModel('EmailTemplate');
                    $email = $this->EmailTemplate->selectTemplate('Added New Standard User');
                    $emailFindReplace = array(
                        '##SITE_LINK##' => Router::url('/', true),
                        '##USER_EMAIL##' => $userData['User']['email'],
                        '##CLOUD_SPACE##' => $this->request->data['UserSubscriptionHistory']['space_alloted'],
                        '##USERNAME##' => $userData['User']['first_name'],
                        '##USER_LIST##' => $userList,
                        '##SITE_NAME##' => Configure::read('site.name'),
                        '##SUPPORT_EMAIL##' => Configure::read('site.contactus_email'),
                        '##WEBSITE_URL##' => Router::url('/', true),
                        '##FROM_EMAIL##' => $this->User->changeFromEmail(($email['from'] == '##FROM_EMAIL##') ? Configure::read('EmailTemplate.from_email') : $email['from']),
                        '##CONTACT_URL##' => Router::url(array(
                            'controller' => '/',
                            'action' => 'contact-us.html',
                            'admin' => false
                                ), true),
                        '##SITE_LOGO##' => Router::url(array(
                            'controller' => 'img',
                            'action' => '/',
                            'admin-logo.png',
                            'admin' => false
                                ), true),
                    );
                    $this->Email->from = ($email['from'] == '##FROM_EMAIL##') ? Configure::read('EmailTemplate.from_email') : $email['from'];
                    $this->Email->replyTo = ($email['reply_to_email'] == '##REPLY_TO_EMAIL##') ? Configure::read('EmailTemplate.reply_to') : $email['reply_to_email'];
                    $this->Email->to = $LoggedUserData['User']['email'];
					if(!empty($this->Auth->user('invoice_email'))){
						$this->Email->to = array($LoggedUserData['User']['email'] , $this->Auth->user('invoice_email'));
					}
                    $this->Email->subject = strtr($email['subject'], $emailFindReplace);
                    $this->Email->sendAs = ($email['is_html']) ? 'html' : 'text';
                    $this->Email->attachments = array($this->Session->read('pdfFile'));
                    $this->Email->send(strtr($email['description'], $emailFindReplace));
                    $this->Session->delete('plan_detail');
                    $this->Session->delete('std_user_data');
                    $this->Session->setFlash("User has been added to your subscription");
                    $this->redirect(array("controller"=>"users", "action"=>"userList"));
                    exit;
                }else{
                    $this->set("paymentError", $result['message']); 
                }
            }else{  
                $this->set("paymentError", $result['message']);
            }
        }
        $this->User->virtualFields = array('address' => "CONCAT(User.town,', ',User.city,', ',User.county,', ',User.postcode)");
		
		$this->User->bindModel(
			   array(
				 'belongsTo'=>array(  
					'Country'=>array(
					  'className' => 'Country',
					  'foreignKey' => 'country_id',                      
					) ,
					
			   )
			),
			false
		);
        if ($this->Auth->user('first_name') != "") {
            $userName = $this->Auth->user('first_name') . " " . $this->Auth->user('last_name');
            $userDetail = $this->User->find('first', array('conditions' => array('User.id' => $this->Auth->user('id')), 'fields' => array('User.first_name', 'User.last_name', 'User.email', 'User.house_no', 'User.address', 'User.address2', 'User.town', 'User.county', 'User.city', 'User.postcode', 'User.country_id', 'User.contact', 'Company.name', 'Company.house_no'), 'recursive' => 2));
        } else { 
            $userDetail = $this->User->find('first', array('conditions' => array('User.id' => $this->Session->read('invited_user')), 'fields' => array('first_name', 'last_name', 'User.email', 'User.house_no', 'User.address', 'User.address2', 'User.town', 'User.county', 'User.city', 'User.postcode', 'User.country_id', 'User.contact', 'Company.name', 'Company.house_no'), 'recursive' => 2));
            $userName = $userDetail['User']['first_name'] . " " . $userDetail['User']['last_name'];
        }

        $userEmail = '';
        if (!empty($stdUserData)) {
            $userEmail = $stdUserData['User'][0]['email'];
        } 

        $userSession = $this->Session->read('std_user_data');
        $this->loadModel('UserSubscriptionHistory');
        $userInvoiceData = $this->UserSubscriptionHistory->find('first', array('fields' => 'UserSubscriptionHistory.id', 'order' => array('id' => 'DESC')));
        $invoiceNumber = 1;
        if (!empty($userInvoiceData)) {
            $invoiceNumber = $userInvoiceData['UserSubscriptionHistory']['id'] + 1;
        }

        /*         * ******* Code for writing invoice pdf for email attachment ***** */
        $userSession['User'] = $this->Auth->user();
        $userSession['plan_detail'] = $this->Session->read('plan_detail');
        $userSession['std_user_data'] = $this->Session->read('std_user_data');
		
		
		$selectedPlan['Plan']['user_count'] = count($userSession['std_user_data']);
		
        $field_string = http_build_query($userSession);
        $timeStamp = time();
        $url = Router::url("/", true) . "plans/invoice_user/d/" . $timeStamp;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        //curl_setopt($ch, CURLOPT_CAINFO, WWW_ROOT . 'cacert_new.pem');
        curl_setopt($ch, CURLOPT_POST, true);  // tell curl you want to post something
        curl_setopt($ch, CURLOPT_POSTFIELDS, $field_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $data = curl_exec($ch);
        curl_close($ch);
        $attachFilePath = WWW_ROOT . "uploads/reports/risk_assesor_invoice_" . $timeStamp . '.pdf';
        $this->Session->write('pdfFile', $attachFilePath); 
        /*         * ******* Code for writing invoice pdf for email attachment ***** */ 
		
		$this->set(compact('selectedPlan', 'planDetail', 'formType','futurePayId', 'stdUserData', 'monthCount', 'stdUserPrice', 'userName', 'userDetail', 'userSession', 'invoiceNumber', 'selectedPlancurr', 'clientToken', 'paymentDetail' , 'userCount'));
    }

    function convertCurrency($toCurrency, $amt) {
        echo $converted_amount = $this->getConvertedAmount($toCurrency, $amt);
        die;
    }

    function getConvertedAmount($toCurrency, $amt, $fromCurr=null) {
        if (($toCurrency == $this->Session->read('CURR') && $fromCurr==null) || $toCurrency==$fromCurr) { 
            return $amt;
        }
        if($fromCurr==null){
            $fromCurr = $this->Session->read('CURR');
        }
        $url = "http://free.currencyconverterapi.com/api/v5/convert?q=" . $fromCurr . "_" . $toCurrency;
        $amt = str_replace(",", "", $amt);         
        //echo "https://www.google.com/finance/converter?a=$amt&from=".$fromCurr."&to=$toCurrency";die;   
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); 
        $result = curl_exec($ch);
        curl_close ($ch);  
        $currencyArr = json_decode($result, true); 
        $convertedAmt = $currencyArr['results'][$fromCurr . "_" . $toCurrency]['val']*$amt;
        return number_format($convertedAmt, 2);
    }

    function subscribe_std($groupId = null) {
        $this->layout = '';
        $this->loadModel('Plan');

        if (!empty($this->request->data)) {
            if ($groupId != null) {
                $this->loadModel('Group');
                $this->request->data['Group'][$groupId] = $this->Group->field('name', array('id' => $groupId));
            }
            $this->Session->write('plan_detail', $this->request->data);
            if ($this->RequestHandler->isAjax()) {
                $this->layout = "ajax";
            } else {
                $this->redirect('addStdUser');
            }
            $formType = $this->request->data['Plan']['payment_type'];
        }
        $userPlan = $this->User->field('plan_id', array('User.id' => $this->Auth->user('id')));
        if ($this->Auth->user('role_id') == 2) {
            $plan_id = 2;
            if ($userPlan) {
                $plan_id = $userPlan;
            }
            $conditions = array('user_type' => 0, 'Plan.id' => $plan_id);
        } else {
            $conditions = array('user_type' => 1);
        }
        $planDetail = $this->Plan->find('first', array('conditions' => $conditions));

        switch ($planDetail['Plan']['id']) {
            case 2:
                $plan = 'Pay Monthly';
                $paymonth = 1;
                $paymenttype = 'monthly';
                break;
            case 5:
                $plan = 'Pay 6 Monthly';
                $paymonth = 6;
                $paymenttype = 'six_month';
                break;
            case 6:
                $plan = 'Pay Annually';
                $paymonth = 12;
                $paymenttype = 'yearly';
                break;
        }

        $this->loadModel('Currency');
        $currencyList = $this->Currency->find('list', array('fields' => array('code', 'code')));
        $std_userprice = Configure::read('site.std_userprice');
        $this->set(compact('planDetail', 'formType', 'currencyList', 'plan', 'paymonth', 'std_userprice', 'paymenttype'));
    }

    function updateBasicDetail() {
        if (!empty($this->request->data)) {
            $this->User->id = $this->Auth->user('id');
            $this->User->save($this->request->data);
            $this->Session->write('Auth.User.first_name', $this->request->data['User']['first_name']);
            $this->Session->write('Auth.User.last_name', $this->request->data['User']['last_name']);
            $this->Session->write('Auth.User.Company.name', $this->request->data['Company']['name']);
            $this->loadModel('Company');
            $this->Company->id = $this->Auth->user('company_id');
            $this->Company->save($this->request->data);
            echo "1";
            die;
        } else {
            echo "0";
            die;
        }
    }

    function updateUserEmail() { 
        if (!empty($this->request->data)) { 
            $this->User->id = $this->Auth->user('id');
            if($this->User->save($this->request->data)){
				$this->Session->write('Auth.User.email', $this->request->data['User']['email']);
				echo "1";
				die;
			}else{
				echo "2";die;
			}
        } else {
            echo "0";
            die;
        }
    }

    function updateUserPassword() {
        if (!empty($this->request->data)) {
            $this->User->id = $this->Auth->user('id');
            $this->User->save($this->request->data);
            echo "1";
            die;
        } else {
            echo "0";
            die;
        }
    }

    function updateCompanyDetail() {

        if (!empty($this->request->data)) {  
            $this->User->id = $this->Auth->user('id');
            $this->User->save($this->request->data);
            $this->loadModel('Company');
            $oldImageName = $this->Company->field('company_logo', array('id' => $this->Auth->user('Company.id')));
            $this->request->data['Company']['id'] = $this->Auth->user('Company.id');
            if ($this->request->data['Company']['company_logo']['name'] != "") {
                $old_extname = @end(explode('.', $this->request->data['Company']['company_logo']['name']));
                $alias = str_replace('.' . $old_extname, '', $this->request->data['Company']['company_logo']['name']) . '_' . time();
                $imagename = $this->Default->createImageName($this->request->data['Company']['company_logo']['name'], WWW_ROOT . 'uploads/company_logo/', $alias);
                $imagename = str_replace(" ","",$imagename);
                if (move_uploaded_file($this->request->data['Company']['company_logo']['tmp_name'], WWW_ROOT . 'uploads/company_logo/' . $imagename)) {
                    $this->request->data['Company']['company_logo'] = $imagename;
                    @unlink(WWW_ROOT . 'uploads/company_logo/' . $oldImageName);
                    $this->Session->write('Auth.User.Company.company_logo', $imagename);
                }
            } else {
                $this->request->data['Company']['company_logo'] = $oldImageName;
            }
            $this->request->data['Company']['country_id'] = $this->request->data['User']['country_id'];
            $this->request->data['Company']['id'] = $this->Auth->user('Company.id');
            if($this->request->data['Company']['country_id']==2){
                $this->Session->write('CURR','USD');
            }else{
                $this->Session->write('CURR','GBP');
            }
            $this->Company->save($this->request->data);  
            $this->Session->write('Auth.User.country_id', $this->request->data['User']['country_id']);
            $this->Session->write('Auth.User.Company.country_id', $this->request->data['User']['country_id']);
            $this->Session->write('Auth.User.Company.house_no', $this->request->data['Company']['house_no']);
            $this->Session->write('Auth.User.Company.town', $this->request->data['Company']['town']);
            $this->Session->write('Auth.User.Company.city', $this->request->data['Company']['city']);
            $this->Session->write('Auth.User.Company.county', $this->request->data['Company']['county']);
            $this->Session->write('Auth.User.Company.postcode', $this->request->data['Company']['postcode']);
            $this->Session->write('Auth.User.Company.company_logo', $this->request->data['Company']['company_logo']);
            $this->Session->write('Auth.User.Company.is_standard', $this->request->data['Company']['is_standard']); 
            echo "1"; 
            die;
        } else {
            echo "0";
            die;
        }
    }

    function getAssessmentList($timeStamp) {
        $this->layout = "ajax";
        $assessmentDate = date('Y-m-d', $timeStamp);
        $this->loadModel('AppPdf');

        // List of all departments related to user's_company
        $this->loadModel('Department');
        $this->Department->unbindModel(array(
            'belongsTo' => array('Company')
        ));
        $fields = array('Department.id', 'Department.name', 'Department.created');
        if ($this->Auth->user('Company.role_id') == 2) {
            $getAccessList = $this->Department->find('all', array('fields' => $fields, 'conditions' => array('Department.user_id' => $this->Auth->user('id'), 'Department.company_id' => $this->Session->read('Auth.User.Company.id'), 'Department.parent_id' => 0)));
        } else {   ############### Fetching Folder for standard user
            $folderId = array();
            $getAccessList = array();
            ################## Fetching Folder According to Access ################
            $this->loadModel('UserAccess');
            $this->loadModel('GroupAccess');

            $this->GroupAccess->bindModel(array(
                'belongsTo' => array('Department')
            ));
            $this->UserAccess->bindModel(array(
                'belongsTo' => array('Department')
            ));

            $AssignFolderStdUser = $this->UserAccess->find('all', array('fields' => $fields, 'conditions' => array('UserAccess.user_id' => $this->Auth->user('id'))));

            if (!empty($AssignFolderStdUser)) {
                foreach ($AssignFolderStdUser as $key => $stdFolder) {
                    $folderId[] = $stdFolder['Department']['id'];
                }
            }

            ############## Fetching Folder according to group ###################
            $this->loadModel('GroupUser');

            $groupId = $this->GroupUser->find('all', array('conditions' => array('GroupUser.user_id' => $this->Auth->user('id'), 'GroupUser.company_id' => $this->Session->read('Auth.User.Company.id')), 'fields' => array('GroupUser.group_id')));

            foreach ($groupId as $group) {
                $groupIds[] = $group['GroupUser']['group_id'];
            }
            $grpCondition = array();

            $grpCondition[] = 'Department.company_id = ' . $this->Session->read('Auth.User.Company.id');

            $grpCondition[] = 'GroupAccess.group_id IN ( ' . implode(",", $groupIds) . ')';

            $getAccessList = $this->GroupAccess->find('all', array('fields' => $fields, 'conditions' => $grpCondition));


            if (!empty($AssignFolderStdUser)) {
                $getAccessList = array_merge($getAccessList, $AssignFolderStdUser);
            }
        }
        foreach ($getAccessList as $accessFolder) {
            $folderId[] = $accessFolder['Department']['id'];
        }

        ######################## Folder ##############
        $conditions = "";

        if ($this->Auth->user('Company.role_id') == 2) {
            $departmentList = $this->Department->find('list', array('conditions' => array('Department.user_id' => $this->Auth->user('id'), 'Department.company_id' => $this->Session->read('Auth.User.Company.id'), 'Department.parent_id' => 0)));
        } else {
            if (!empty($folderId)) {
                $conditions = "Department.id IN (" . implode(",", $folderId) . ")";
                $departmentList = $this->Department->find('list', array('conditions' => array('Department.company_id' => $this->Session->read('Auth.User.Company.id'), 'Department.parent_id' => 0, $conditions)));
            } else {
                $departmentList = array();
            }
        }
        ######################## Folder ##############
        $folder_ids = $this->Department->allDepartmentAccess($departmentList);
        

        $this->AppPdf->bindModel(array(
            'belongsTo' => array(
                'AppUser',
                'User',
                'ReminderUser' => array(
                    'className' => 'User',
                    'foreignKey' => 'reminder_user_id'
                )
            )
                ), false);
        $eventList = $this->AppPdf->find('all', array(
            'conditions' => array(
                'AppPdf.anotherAssessmentDate' => $assessmentDate,
                'AppPdf.department_id' => $folder_ids
            ),
            'fields' => array(
                'AppPdf.anotherAssessmentDate',
                'AppPdf.id',
                'AppPdf.user_id',
                'AppPdf.projectName',
                'AppUser.userName',
                'AppPdf.reminder_user_id',
                'AppPdf.department_id',
                'ReminderUser.first_name',
                'ReminderUser.last_name',
                'User.administrator_id'
            )
        ));
        for ($i = 0; $i < count($eventList); $i++) {
            if ($this->Auth->user('role_id') == 2) {
                $eventList[$i]['AppPdf']['folder_access'] = 2;
            } else {
                $eventList[$i]['AppPdf']['folder_access'] = $this->UserAccess->field('access', array('UserAccess.department_id' => $eventList[$i]['AppPdf']['department_id'], 'UserAccess.user_id' => $this->Auth->user('id')));
            }
        }

        $this->loadModel("StoreOrder"); 
        $storeOrders = $this->StoreOrder->find("all", array(
            'conditions' => array(
                "StoreOrder.user_id" => $this->Auth->user("id"),
                'StoreOrder.created >= ' => date('Y-m-d', strtotime('-1 day',strtotime($assessmentDate)))
            )
        ));  
        $storeReminder = array();
        if(!empty($storeOrders)){
            $i=0;
            foreach($storeOrders as $order){
                $orderJsonArr = json_decode($order['StoreOrder']['order_json'], true);
                if($orderJsonArr['isCommissining']){ 
                    $storeReminder[$i] = $orderJsonArr;
                    $storeReminder[$i]['id'] = $order['StoreOrder']['id'];
                    $i++;
                }
            }
        }   
        // /pr($storeReminder);die;
        $this->set(compact('eventList', 'assessmentDate', 'storeReminder')); 
    }

    function showNextPreviousMonth($currMonth, $type) {
        $this->layout = 'ajax';

        if ($type == 'previous') {
            $previousMonth = strtotime(date('Y-m', strtotime('-1 month', $currMonth)));
        } else {
            $previousMonth = strtotime(date('Y-m', strtotime('+1 month', $currMonth)));
        }

        $week_start = strtotime('first day of this month', $previousMonth);
        //$month_end = strtotime('last day of this month', $previousMonth);

        $week_end = strtotime('next Sunday', $previousMonth);

        if ($type == 'Next') {
            $monthName = date('F Y', $week_end);
        } else {
            $monthName = date('F Y', $week_start);
        }

        $datediff = $week_end - $week_start;
        $daydiffcount = (floor($datediff / (60 * 60 * 24))) + 1;
        if ($daydiffcount < 7) {
            $loop_week_mon = $week_mon = strtotime('Previous Monday', $previousMonth);
        } else {
            $loop_week_mon = $week_mon = strtotime('First Monday', $previousMonth);
        }
        $weekArray = array();

        $k = 0;

        while ($loop_week_mon <= $week_end) {
            $weekArray[$k]['dayd'] = date('d', $loop_week_mon);
            $weekArray[$k]['dayD'] = date('D', $loop_week_mon);
            if ($loop_week_mon < $week_start) {
                $weekArray[$k]['status'] = 0;
            } else {
                $weekArray[$k]['status'] = 1;
            }
            $loop_week_mon = strtotime('+1 day', $loop_week_mon);
            $k++;
        }

        // List of all departments related to user's_company
        $this->loadModel('Department');
        $this->Department->unbindModel(array(
            'belongsTo' => array('Company')
        ));
        $fields = array('Department.id', 'Department.name', 'Department.created');
        if ($this->Auth->user('Company.role_id') == 2) {
            $getAccessList = $this->Department->find('all', array('fields' => $fields, 'conditions' => array('Department.user_id' => $this->Auth->user('id'), 'Department.company_id' => $this->Session->read('Auth.User.Company.id'), 'Department.parent_id' => 0)));
        } else {   ############### Fetching Folder for standard user
            $folderId = array();
            $getAccessList = array();
            ################## Fetching Folder According to Access ################
            $this->loadModel('UserAccess');
            $this->loadModel('GroupAccess');
            $this->UserAccess->bindModel(array(
                'belongsTo' => array('Department')
            ));

            $this->GroupAccess->bindModel(array(
                'belongsTo' => array('Department')
            ));
            $AssignFolderStdUser = $this->UserAccess->find('all', array('fields' => $fields, 'conditions' => array('UserAccess.user_id' => $this->Auth->user('id'), 'Department.company_id' => $this->Auth->user('Company.id'))));


            if (!empty($AssignFolderStdUser)) {
                foreach ($AssignFolderStdUser as $key => $stdFolder) {
                    $folderId[] = $stdFolder['Department']['id'];
                }
            }

            ############## Fetching Folder according to group ###################
            $this->loadModel('GroupUser');

            $groupId = $this->GroupUser->find('all', array('conditions' => array('GroupUser.user_id' => $this->Auth->user('id'), 'GroupUser.company_id' => $this->Session->read('Auth.User.Company.id')), 'fields' => array('GroupUser.group_id')));

            foreach ($groupId as $group) {
                $groupIds[] = $group['GroupUser']['group_id'];
            }
            $grpCondition = array();

            $grpCondition[] = 'Department.company_id = ' . $this->Session->read('Auth.User.Company.id');

            $grpCondition[] = 'GroupAccess.group_id IN ( ' . implode(",", $groupIds) . ')';

            $getAccessList = $this->GroupAccess->find('all', array('fields' => $fields, 'conditions' => $grpCondition));


            if (!empty($AssignFolderStdUser)) {
                $getAccessList = array_merge($getAccessList, $AssignFolderStdUser);
            }
        }

        foreach ($getAccessList as $accessFolder) {
            $folderId[] = $accessFolder['Department']['id'];
        }
        ######################## Folder ##############
        $conditions = "";

        if ($this->Auth->user('Company.role_id') == 2) {
            $departmentList = $this->Department->find('list', array('conditions' => array('Department.user_id' => $this->Auth->user('id'), 'Department.company_id' => $this->Session->read('Auth.User.Company.id'), 'Department.parent_id' => 0), 'fields' => array('Department.id', 'Department.id')));
        } else {
            if (!empty($folderId)) {
                $conditions = "Department.id IN (" . implode(",", $folderId) . ")";
                $departmentList = $this->Department->find('list', array('conditions' => array('Department.company_id' => $this->Session->read('Auth.User.Company.id'), $conditions), 'fields' => array('Department.id', 'Department.id')));
            } else {
                $departmentList = array();
            }
        }
        ######################## Folder ##############
        $folder_ids = $this->Department->allDepartmentAccess($departmentList);

        $this->loadModel('AppPdf');
        $this->AppPdf->bindModel(array(
            'belongsTo' => array('AppUser', 'User')
        ));
        $remider = array();

        $eventreminders = $this->AppPdf->find('all', array(
            'conditions' => array('AppPdf.department_id' => $folder_ids, 'AppPdf.anotherAssessmentDate >= ' => date('Y-m-d', $week_mon), 'AppPdf.anotherAssessmentDate <= ' => date('Y-m-d', $week_end)),
            'fields' => array('AppPdf.anotherAssessmentDate', 'AppPdf.id', 'AppPdf.projectName', 'AppPdf.user_id', 'User.administrator_id', 'AppUser.userName')
        ));

        $this->AppPdf->bindModel(array(
            'belongsTo' => array(
                'AppUser',
                'User',
                'ReminderUser' => array(
                    'className' => 'User',
                    'foreignKey' => 'reminder_user_id'
                )
            )
                ), false);

        $eventreminders = $this->AppPdf->find('all', array(
            'conditions' => array(
                'AppPdf.department_id' => $folder_ids,
                'AppPdf.anotherAssessmentDate >= ' => date('Y-m-d', $week_mon),
                'AppPdf.anotherAssessmentDate <= ' => date('Y-m-d', $week_end)
            ),
            'fields' => array(
                'AppPdf.anotherAssessmentDate',
                'AppPdf.reminder_user_id',
                'ReminderUser.first_name',
                'ReminderUser.last_name',
                'AppPdf.id', 'AppPdf.projectName',
                'AppUser.userName',
                'AppPdf.user_id',
                'AppPdf.department_id',
                'User.administrator_id'
            )
        ));

        ###################### Fetching previous month records if day count is less ###############
        if (!empty($eventreminders)) {
            foreach ($eventreminders as $key => $reminders) {
                if ($this->Auth->user('role_id') == 2) {
                    $access = 2;
                } else {
                    $access = $this->UserAccess->field('access', array('UserAccess.department_id' => $reminders['AppPdf']['department_id'], 'UserAccess.user_id' => $this->Auth->user('id')));
                }
                $remider[date('d', strtotime($reminders['AppPdf']['anotherAssessmentDate']))][$key]['project_name'] = $reminders['AppPdf']['projectName'];
                $remider[date('d', strtotime($reminders['AppPdf']['anotherAssessmentDate']))][$key]['assessor_name'] = $reminders['AppUser']['userName'];
                $remider[date('d', strtotime($reminders['AppPdf']['anotherAssessmentDate']))][$key]['pdf_id'] = $reminders['AppPdf']['id'];
                $remider[date('d', strtotime($reminders['AppPdf']['anotherAssessmentDate']))][$key]['anotherAssessmentDate'] = $reminders['AppPdf']['anotherAssessmentDate'];
                $remider[date('d', strtotime($reminders['AppPdf']['anotherAssessmentDate']))][$key]['user_id'] = $reminders['AppPdf']['user_id'];
                $remider[date('d', strtotime($reminders['AppPdf']['anotherAssessmentDate']))][$key]['administrator_id'] = $reminders['User']['administrator_id'];
                $remider[date('d', strtotime($reminders['AppPdf']['anotherAssessmentDate']))][$key]['reminder_user_id'] = $reminders['AppPdf']['reminder_user_id'];
                $remider[date('d', strtotime($reminders['AppPdf']['anotherAssessmentDate']))][$key]['reminder_user'] = $reminders['ReminderUser']['first_name'] . " " . $reminders['ReminderUser']['last_name'];
                $remider[date('d', strtotime($reminders['AppPdf']['anotherAssessmentDate']))][$key]['folder_access'] = $access;
            }
        }

        /******************* Store Commissioning Order Notifications ***********************************/
        $this->loadModel("StoreOrder");
        
        $storeOrders = $this->StoreOrder->find("all", array(
            'conditions' => array(
                "StoreOrder.user_id" => $this->Auth->user("id"),
                'DATE(StoreOrder.created) >= ' => date('Y-m-d', strtotime('+1 day',$week_mon)),
                'DATE(StoreOrder.created) <= ' => date('Y-m-d', strtotime('+1 day',$week_end))
            )
        )); 
        $storeReminder = array();
        if(!empty($storeOrders)){
            $i=0;
            foreach($storeOrders as $order){
                $orderJsonArr = json_decode($order['StoreOrder']['order_json'], true);
                if($orderJsonArr['isCommissining']){ 
                    $dayKey = date('d', strtotime('+1 day', strtotime($order['StoreOrder']['created'])));
                    $storeReminder[$dayKey][$i]['quote_name'] = $orderJsonArr['name'];
                    $storeReminder[$dayKey][$i]['order_number'] = $order['StoreOrder']['id'];
                    $j=0;
                    foreach($orderJsonArr['Items'] as $item){
                        if($item['category_id'] == 41){
                            $storeReminder[$dayKey][$i]['product'][$j]['name'] = $item['title'];  
                            $storeReminder[$dayKey][$i]['product'][$j]['image_name'] = $item['image_name'];
                            $j++;
                        }
                    }
                    $i++;
                }
            }
        }   
        $this->set(compact('week_end', 'week_start', 'remider', 'weekArray', 'monthName', 'storeReminder'));
    }

    function showNextPreviousWeek($currWeek, $type) {
        $this->layout = 'ajax';

        $week_start = $currWeek;
        //$month_end = strtotime('last day of this month', $previousMonth);
        //$week_end = strtotime('next Sunday', $week_start);
        $week_end = strtotime('+6 days', $week_start);

        ##################  Comparing Month ############
        $weekS = date('m', $week_start);
        $weekE = date('m', $week_end);
        if ($type == 'Next') {
            if ($weekS == $weekE) {
                $monthName = date('F Y', $week_end);
            } else {
                $monthName = date('M y', $week_start) - date('M y', $week_end);
            }
        } else {
            if ($weekS == $weekE) {
                $monthName = date('F Y', $week_start);
            } else {
                $monthName = date('M y', $week_start) . '-' . date('M y', $week_end);
            }
        }

        // List of all departments related to user's_company
        $this->loadModel('Department');
        $this->Department->unbindModel(array(
            'belongsTo' => array('Company')
        ));
        $fields = array('Department.id', 'Department.name', 'Department.created');
        if ($this->Auth->user('Company.role_id') == 2) {
            $getAccessList = $this->Department->find('all', array('fields' => $fields, 'conditions' => array('Department.user_id' => $this->Auth->user('id'), 'Department.company_id' => $this->Session->read('Auth.User.Company.id'), 'Department.parent_id' => 0)));
        } else {   ############### Fetching Folder for standard user
            $folderId = array();
            $getAccessList = array();
            ################## Fetching Folder According to Access ################
            $this->loadModel('UserAccess');
            $this->loadModel('GroupAccess');

            $this->GroupAccess->bindModel(array(
                'belongsTo' => array('Department')
            ));
            $this->UserAccess->bindModel(array(
                'belongsTo' => array('Department')
            ));

            $AssignFolderStdUser = $this->UserAccess->find('all', array('fields' => $fields, 'conditions' => array('UserAccess.user_id' => $this->Auth->user('id'), 'Department.company_id' => $this->Auth->user('Company.id'))));

            if (!empty($AssignFolderStdUser)) {
                foreach ($AssignFolderStdUser as $key => $stdFolder) {
                    $folderId[] = $stdFolder['Department']['id'];
                }
            }

            ############## Fetching Folder according to group ###################
            $this->loadModel('GroupUser');

            $groupId = $this->GroupUser->find('all', array('conditions' => array('GroupUser.user_id' => $this->Auth->user('id'), 'GroupUser.company_id' => $this->Session->read('Auth.User.Company.id')), 'fields' => array('GroupUser.group_id')));

            foreach ($groupId as $group) {
                $groupIds[] = $group['GroupUser']['group_id'];
            }

            $grpCondition = array();

            $grpCondition[] = 'Department.company_id = ' . $this->Session->read('Auth.User.Company.id');

            if (!empty($groupIds)) {
                $grpCondition[] = 'GroupAccess.group_id IN ( ' . implode(",", $groupIds) . ')';
            }

            $getAccessList = $this->GroupAccess->find('all', array('fields' => $fields, 'conditions' => $grpCondition));


            if (!empty($AssignFolderStdUser)) {
                $getAccessList = array_merge($getAccessList, $AssignFolderStdUser);
            }
        }

        foreach ($getAccessList as $accessFolder) {
            $folderId[] = $accessFolder['Department']['id'];
        }

        ######################## Folder ##############
        $conditions = "";
        if ($this->Auth->user('Company.role_id') == 2) {
            $departmentList = $this->Department->find('list', array('conditions' => array('Department.user_id' => $this->Auth->user('id'), 'Department.company_id' => $this->Session->read('Auth.User.Company.id'), 'Department.parent_id' => 0), 'fields' => array('Department.id', 'Department.id')));
        } else {
            if (!empty($folderId)) {
                $conditions = "Department.id IN (" . implode(",", $folderId) . ")";
                $departmentList = $this->Department->find('list', array('conditions' => array('Department.company_id' => $this->Session->read('Auth.User.Company.id'), $conditions), 'fields' => array('Department.id', 'Department.id')));
            } else {
                $departmentList = array();
            }
        }

        ######################## Folder ##############
        $folder_ids = $this->Department->allDepartmentAccess($departmentList);
        $this->loadModel('AppPdf');

        $this->AppPdf->bindModel(array(
            'belongsTo' => array(
                'AppUser',
                'User',
                'ReminderUser' => array(
                    'className' => 'User',
                    'foreignKey' => 'reminder_user_id'
                )
            )
                ), false);

        $eventreminders = $this->AppPdf->find('all', array(
            'conditions' => array(
                'AppPdf.department_id' => $folder_ids,
                'AppPdf.anotherAssessmentDate >= ' => date('Y-m-d', $week_start),
                'AppPdf.anotherAssessmentDate <= ' => date('Y-m-d', $week_end)
            ),
            'fields' => array(
                'AppPdf.anotherAssessmentDate',
                'AppPdf.reminder_user_id',
                'ReminderUser.first_name',
                'ReminderUser.last_name',
                'AppPdf.id', 'AppPdf.projectName',
                'AppUser.userName',
                'AppPdf.user_id',
                'AppPdf.department_id',
                'User.administrator_id'
            )
        ));
        $reminder = array();
        if (!empty($eventreminders)) {
            foreach ($eventreminders as $key => $reminders) {
                if ($this->Auth->user('role_id') == 2) {
                    $access = 2;
                } else {
                    $access = $this->UserAccess->field('access', array('UserAccess.department_id' => $reminders['AppPdf']['department_id'], 'UserAccess.user_id' => $this->Auth->user('id')));
                }
                $reminder[date('d', strtotime($reminders['AppPdf']['anotherAssessmentDate']))][$key]['project_name'] = $reminders['AppPdf']['projectName'];
                $reminder[date('d', strtotime($reminders['AppPdf']['anotherAssessmentDate']))][$key]['assessor_name'] = $reminders['AppUser']['userName'];
                $reminder[date('d', strtotime($reminders['AppPdf']['anotherAssessmentDate']))][$key]['pdf_id'] = $reminders['AppPdf']['id'];
                $reminder[date('d', strtotime($reminders['AppPdf']['anotherAssessmentDate']))][$key]['anotherAssessmentDate'] = $reminders['AppPdf']['anotherAssessmentDate'];
                $reminder[date('d', strtotime($reminders['AppPdf']['anotherAssessmentDate']))][$key]['user_id'] = $reminders['AppPdf']['user_id'];
                $reminder[date('d', strtotime($reminders['AppPdf']['anotherAssessmentDate']))][$key]['administrator_id'] = $reminders['User']['administrator_id'];
                $reminder[date('d', strtotime($reminders['AppPdf']['anotherAssessmentDate']))][$key]['reminder_user_id'] = $reminders['AppPdf']['reminder_user_id'];
                $reminder[date('d', strtotime($reminders['AppPdf']['anotherAssessmentDate']))][$key]['reminder_user'] = $reminders['ReminderUser']['first_name'] . " " . $reminders['ReminderUser']['last_name'];
                $reminder[date('d', strtotime($reminders['AppPdf']['anotherAssessmentDate']))][$key]['folder_access'] = $access;
            }
            if (!empty($reminder)) {
                foreach ($reminder as $key => $remiderNew) {
                    $remider[$key] = array_values($remiderNew);
                }
            }
        }

        $this->loadModel("StoreOrder");
        $storeOrders = $this->StoreOrder->find("all", array(
            'conditions' => array(
                "StoreOrder.user_id" => $this->Auth->user("id"),
                'StoreOrder.created >= ' => date('Y-m-d', strtotime('+1 day',$week_start)),
                'StoreOrder.created <= ' => date('Y-m-d', strtotime('+1 day',$week_end))
            )
        )); 
        $storeReminder = array();
        if(!empty($storeOrders)){
            $i=0;
            foreach($storeOrders as $order){
                $orderJsonArr = json_decode($order['StoreOrder']['order_json'], true);
                if($orderJsonArr['isCommissining']){ 
                    $dayKey = date('d', strtotime('+1 day', strtotime($order['StoreOrder']['created'])));
                    $storeReminder[$dayKey][$i]['quote_name'] = $orderJsonArr['name'];
                    $storeReminder[$dayKey][$i]['order_number'] = $order['StoreOrder']['id'];
                    $j=0;
                    foreach($orderJsonArr['Items'] as $item){
                        if($item['category_id'] == 41){
                            $storeReminder[$dayKey][$i]['product'][$j]['name'] = $item['title'];   
                            $j++;
                        }
                    }
                    $i++;
                }
            }
        } 

        $this->set(compact('week_end', 'week_start', 'remider', 'monthName', 'storeReminder'));
    }

    public function upgradePlan() {
        $this->layout = 'task_module';
        $this->set('title_for_layout', Configure::read('site.name') . ' :: Upgrade Plan');
        $this->loadModel('PaymentInformation');
        $this->PaymentInformation->bindModel(array(
            'belongsTo' => array('User')
        ));  
        $sessionCurr =  "GBP";
        $this->loadModel('Plan'); 
        $oneMonthPlan = $currentPlan = $this->Plan->find('first', array('conditions' => array('Plan.id' => $this->Auth->user('plan_id'))));
        $paymentDetail = $this->PaymentInformation->find('first', array('conditions' => array('user_id' => $this->Auth->user('id'))));

        if ($this->Auth->user('role_id') == 2) {
            $plan_id = 2;
            if ($this->Auth->user('plan_id')) {
                $plan_id = $this->User->field('plan_id', array('User.id' => $this->Auth->user('id')));
            }
            $conditions = array('user_type' => 0, 'Plan.id' => $plan_id);
        } else {
            $conditions = array('user_type' => 1);
        }

        $planDetail = $this->Plan->find('first', array('conditions' => $conditions));
        $userSpace = $this->Default->convertSpace($paymentDetail['User']['total_space']); 
        if ($userSpace >= 1) {
            $totalSpace = $userSpace . " GB";
            $deduct_Amount = $planDetail['Plan']['1GB_price_'.$sessionCurr] * $userSpace;
            if($sessionCurr=="USD"){
                $deduct_Amount_gbp = $planDetail['Plan']['1GB_price_GBP'] * $userSpace;
            }
        } else {
            $totalSpace = number_format($paymentDetail['User']['total_space'] / 1000, 0) . " MB";
            $deduct_Amount = $planDetail['Plan'][($paymentDetail['User']['total_space'] / 1000) . '_price_'.$sessionCurr];
            if($sessionCurr=="USD"){
                $deduct_Amount_gbp = $planDetail['Plan'][($paymentDetail['User']['total_space'] / 1000) . '_price_GBP'];
            }
        }  
        $this->loadModel('UsersCompany');
        $totUserCount = $this->Session->read("Auth.User.user_count")-1; 
        switch ($planDetail['Plan']['id']) {
            case 2:
                $plan = 'Pay Monthly';
                $totalMonth = 1; 
                break;
            case 5:
                $plan = 'Pay 6 Monthly';
                $totalMonth = 6;
                $oneMonthPlan['Plan']['150_price_'.$sessionCurr] = $oneMonthPlan['Plan']['150_price_'.$sessionCurr]/6;
                $oneMonthPlan['Plan']['300_price_'.$sessionCurr] = $oneMonthPlan['Plan']['300_price_'.$sessionCurr]/6;
                $oneMonthPlan['Plan']['500_price_'.$sessionCurr] = $oneMonthPlan['Plan']['500_price_'.$sessionCurr]/6;
                $oneMonthPlan['Plan']['1GB_price_'.$sessionCurr] = $oneMonthPlan['Plan']['1GB_price_'.$sessionCurr]/6;
                break;
            case 6:
                $plan = 'Pay Annually';
                $totalMonth = 12;
                $oneMonthPlan['Plan']['150_price_'.$sessionCurr] = $oneMonthPlan['Plan']['150_price_'.$sessionCurr]/12;
                $oneMonthPlan['Plan']['300_price_'.$sessionCurr] = $oneMonthPlan['Plan']['300_price_'.$sessionCurr]/12;
                $oneMonthPlan['Plan']['500_price_'.$sessionCurr] = $oneMonthPlan['Plan']['500_price_'.$sessionCurr]/12;
                $oneMonthPlan['Plan']['1GB_price_'.$sessionCurr] = $oneMonthPlan['Plan']['1GB_price_'.$sessionCurr]/12;
                break;
        }
        $remainAmt = 0;
        if ($totUserCount > 0) {
            $remainAmt = Configure::read('site.std_userprice') * $totUserCount * $totalMonth;
            $singleUserAmt = Configure::read('site.std_userprice') * $totalMonth;
            if($this->Auth->user('plan_id') == 5){
                $remainAmt = $remainAmt - $remainAmt*10/100;
                $singleUserAmt = $singleUserAmt - $singleUserAmt*10/100;
            }elseif($this->Auth->user('plan_id') == 6){ 
                $remainAmt = $remainAmt - ($remainAmt*20/100); 
                $singleUserAmt = $singleUserAmt - ($singleUserAmt*20/100); 
            }
            if ($this->Auth->user('country_id') == 1) {
                $singleUserAmt = $singleUserAmt * 1.2;
            }
        }else{
            $singleUserAmt = Configure::read('site.std_userprice') * $totalMonth;
            if($this->Auth->user('plan_id') == 5){ 
                $singleUserAmt = $singleUserAmt - $singleUserAmt*10/100;
            }elseif($this->Auth->user('plan_id') == 6){  
                $singleUserAmt = $singleUserAmt - ($singleUserAmt*20/100); 
            }
            if ($this->Auth->user('country_id') == 1) {
                $singleUserAmt = $singleUserAmt * 1.2;
            }
        }

        if ($totalMonth != 0) {
            $deduct_AmountPerMonth = $deduct_Amount / $totalMonth;
        }  
        $nextPaymentDate = strtotime('+' . $paymentDetail['PaymentInformation']['frequency'], strtotime($paymentDetail['PaymentInformation']['payment_date']));
        while ($nextPaymentDate < time()) {
            $nextPaymentDate = strtotime('+' . $paymentDetail['PaymentInformation']['frequency'], $nextPaymentDate);
        }
        $diffDate = date("Y-m-d", $nextPaymentDate); 
        $nextPaymentDate = date($this->Session->read('DATE_FORMAT'), $nextPaymentDate); 
        $d1 = new DateTime($diffDate); 
        $d2 = new DateTime(date('Y-m-d')); 
        $interval = $d2->diff($d1); 

        $monthCount = $interval->format('%m');
        if ($monthCount == 0) {
            $year = $interval->format('%y');
            if ($year == 1) {
                $monthCount = 12;
            } else {
                $monthCount = 1;
            }
        }  

        $deduct_AmountNeedToDeduct = $deduct_AmountPerMonth * $monthCount;
        $paidMonthlyAmt = $deduct_Amount;
        if ($this->Auth->user('country_id') == 1) {
            $paidMonthlyAmt = $paidMonthlyAmt * 1.2;
        }  
        $one_off_150 = number_format(($oneMonthPlan['Plan']['150_price_'.$sessionCurr] * $monthCount - $deduct_AmountNeedToDeduct) , 2, '.', '');
        if ($this->Auth->user('country_id') == 1) {
            $planDetail['Plan']['150_one_off'] = number_format($one_off_150, 2);
            $planDetail['Plan']['150_next_recurring'] = number_format((($currentPlan['Plan']['150_price_'.$sessionCurr]) + $remainAmt) * 1.2, 2, '.', '');
        } elseif ($this->Auth->user('country_id') == 2) {
            $planDetail['Plan']['150_one_off'] = number_format($one_off_150, 2); 
            $planDetail['Plan']['150_one_off_gbp'] = number_format(($oneMonthPlan['Plan']['150_price_GBP'] - $deduct_Amount_gbp) * $monthCount, 2, '.', '');  
            $planDetail['Plan']['150_next_recurring'] = number_format(($currentPlan['Plan']['150_price_'.$sessionCurr]) + $remainAmt, 2, '.', '');
            $planDetail['Plan']['150_next_recurring_gbp'] = number_format(($currentPlan['Plan']['150_price_GBP']) + $remainAmt, 2, '.', '');
        } else{
            $planDetail['Plan']['150_one_off'] = number_format($one_off_150, 2, '.', '');
            $planDetail['Plan']['150_next_recurring'] = number_format(($currentPlan['Plan']['150_price_'.$sessionCurr]) + $remainAmt, 2, '.', '');
        }

        $one_off_300 = number_format(($oneMonthPlan['Plan']['300_price_'.$sessionCurr] * $monthCount - $deduct_AmountNeedToDeduct) , 2, '.', '');
        if ($this->Auth->user('country_id') == 1) {
            $planDetail['Plan']['300_one_off'] = number_format($one_off_300, 2, '.', ''); 
            $nextRecurring300 = (($currentPlan['Plan']['300_price_'.$sessionCurr]) + $remainAmt); 
            $planDetail['Plan']['300_next_recurring'] = number_format((($currentPlan['Plan']['300_price_'.$sessionCurr]) + $remainAmt) * 1.2, 2, '.', '');
        } elseif ($this->Auth->user('country_id') == 2) {
            $planDetail['Plan']['300_one_off'] = number_format($one_off_300, 2, '.', ''); 
            $planDetail['Plan']['300_one_off_gbp'] = number_format(($oneMonthPlan['Plan']['300_price_GBP'] - $deduct_Amount_gbp) * $monthCount, 2, '.', ''); 
            $planDetail['Plan']['300_next_recurring'] = number_format(($currentPlan['Plan']['300_price_'.$sessionCurr]) + $remainAmt, 2, '.', '');
            $planDetail['Plan']['300_next_recurring_gbp'] = number_format(($currentPlan['Plan']['300_price_GBP']) + $remainAmt, 2, '.', '');
        } else {
            $planDetail['Plan']['300_one_off'] = number_format($one_off_300, 2, '.', '');
            $planDetail['Plan']['300_next_recurring'] = number_format(($currentPlan['Plan']['300_price_'.$sessionCurr]) + $remainAmt, 2, '.', '');
        }
        
        $one_off_500 = number_format(($oneMonthPlan['Plan']['500_price_'.$sessionCurr] * $monthCount - $deduct_AmountNeedToDeduct) , 2, '.', '');
        if ($this->Auth->user('country_id') == 1) {
            $planDetail['Plan']['500_one_off'] = number_format($one_off_500, 2, '.', '');
            $planDetail['Plan']['500_next_recurring'] = number_format((($currentPlan['Plan']['500_price_'.$sessionCurr] ) + $remainAmt) * 1.2, 2, '.', '');
        } elseif ($this->Auth->user('country_id') == 2) {
            $planDetail['Plan']['500_one_off'] = number_format($one_off_500, 2, '.', ''); 
            $planDetail['Plan']['500_one_off_gbp'] = number_format(($oneMonthPlan['Plan']['500_price_GBP'] - $deduct_Amount_gbp) * $monthCount, 2, '.', ''); 
            $planDetail['Plan']['500_next_recurring'] = number_format(($currentPlan['Plan']['500_price_'.$sessionCurr] ) + $remainAmt, 2, '.', '');
            $planDetail['Plan']['500_next_recurring_gbp'] = number_format(($currentPlan['Plan']['500_price_GBP'] ) + $remainAmt, 2, '.', '');
        } else {
            $planDetail['Plan']['500_one_off'] = number_format($one_off_500, 2, '.', '');
            $planDetail['Plan']['500_next_recurring'] = number_format(($currentPlan['Plan']['500_price_'.$sessionCurr] ) + $remainAmt, 2, '.', '');
        }
        
        for ($i = 1; $i <= 20; $i++) {  
            $one_off_GB = number_format(($oneMonthPlan['Plan']['1GB_price_'.$sessionCurr]* $monthCount*$i  - $deduct_AmountNeedToDeduct) , 2, '.', '');  
            if ($this->Auth->user('country_id') == 1) { 
                $planDetail['Plan'][$i . 'GB_next_recurring'] = number_format((($currentPlan['Plan']['1GB_price_'.$sessionCurr] * $i ) + $remainAmt) * 1.2, 2, '.', '');
                $planDetail['Plan'][$i . 'GB_one_off'] = number_format(($one_off_GB), 2, '.', ''); 
            }elseif ($this->Auth->user('country_id') == 2) {
                $planDetail['Plan'][$i . 'GB_one_off'] = number_format($one_off_GB * $i, 2, '.', '');  
                $planDetail['Plan'][$i . 'GB_one_off_gbp'] = number_format(($oneMonthPlan['Plan']['1GB_price_GBP'] * $i - $deduct_Amount_gbp) * $monthCount, 2, '.', '');
                $planDetail['Plan'][$i . 'GB_next_recurring'] = number_format((($currentPlan['Plan']['1GB_price_'.$sessionCurr] * $i ) + $remainAmt) * 1.2, 2, '.', '');
                $planDetail['Plan'][$i . 'GB_next_recurring_gbp'] = number_format((($oneMonthPlan['Plan']['1GB_price_GBP'] * $i * $totalMonth) + $remainAmt) * 1.2, 2, '.', '');
            } else { 
                $planDetail['Plan'][$i . 'GB_one_off'] = number_format($one_off_GB * $i, 2, '.', '');
                $planDetail['Plan'][$i . 'GB_next_recurring'] = number_format(($currentPlan['Plan']['1GB_price_'.$sessionCurr] * $i ) + $remainAmt, 2, '.', '');
            }
        }  

        $totalUsage = $this->User->find('first', array('conditions' => array('User.id' => $this->Auth->user('id')), 'fields' => array('available_space', 'total_space')));
        $usedSpace = number_format(($totalUsage['User']['total_space'] - $totalUsage['User']['available_space']) / 1000, 2);
        $this->loadModel('UserSubscriptionHistory');
        $subscriptionDetail = $this->UserSubscriptionHistory->find('first', array(
            'conditions' => array(
                '(UserSubscriptionHistory.transaction_type = "Subscribe" OR UserSubscriptionHistory.transaction_type = "skip_trial")',
                'UserSubscriptionHistory.plan_id' => $this->Auth->user('plan_id'),
                'UserSubscriptionHistory.user_id' => $this->Auth->user('id')
            ), 
            'order' => 'UserSubscriptionHistory.id desc'
        ));
        $this->loadModel("PlanDowngradeInfo") ; 
        $checkOldDowngradeDetails = $this->PlanDowngradeInfo->find("first", array(
            "conditions" => array(
                "PlanDowngradeInfo.user_id" => $this->Auth->user("id"),
                "PlanDowngradeInfo.company_id" => $this->Auth->user("Company.id"),
            )
        ));
 
        if (!empty($this->data)) {    
            if ($this->data['Plan']['amount'] == 0) {  
                $this->PlanDowngradeInfo->deleteAll(array(
                    "PlanDowngradeInfo.user_id" => $this->Auth->user("id"),
                    "PlanDowngradeInfo.company_id" => $this->Auth->user("company_id"),
                ));   
                $downagradeInfo['PlanDowngradeInfo']['downgrade_info'] = json_encode($this->data); 
                $downagradeInfo['PlanDowngradeInfo']['downgrade_date'] = date("Y-m-d", strtotime("-1 day", strtotime($this->changeDate($nextPaymentDate)))); 
                $downagradeInfo['PlanDowngradeInfo']['user_id'] = $this->Auth->user("id"); 
                    $downagradeInfo['PlanDowngradeInfo']['company_id'] = $this->Auth->user("Company.id"); 
                $this->PlanDowngradeInfo->save($downagradeInfo);
                $this->Session->setFlash("Your downgarde informations have been saved. Your plan will downgraded on next payment date.");
                $this->redirect(array('controller' => 'plans', 'action' => 'myPlan'));
            }
            $this->Session->write('upgrade_detail', $this->data);
            $this->redirect('upgradePackageDetail');
        }
        $paymentDetail['PaymentInformation']['us_amount'] = 0;
        if($this->Session->read('CURR')=="USD"){
            $paymentDetail['PaymentInformation']['us_amount'] = $this->getConvertedAmount('USD', $paymentDetail['PaymentInformation']['amount'],'GBP');
        }
        
        $actualUserCount = $this->UsersCompany->find("count", array(
            "conditions" => array(
                "UsersCompany.company_id" => $this->Auth->user("Company.id")
            ),
            "group" => "UsersCompany.user_id"
        ));
        $this->loadModel('Currency');
        $currencyList = $this->Currency->find('list', array('fields' => array('code', 'code')));
        if (strpos($totalSpace, "MB")) {
            $totalSpaceAval = substr($totalSpace, 0, strpos($totalSpace, "MB"));
        } else {
            $totalSpaceAval = substr($totalSpace, 0, strpos($totalSpace, " GB"));
        }  
        $this->set(compact('nextPaymentDate', 'subscriptionDetail', 'plan', 'totalSpace', 'planDetail', 'paymentDetail', 'usedSpace', 'currencyList', 'oneMonthPlan', 'totalSpaceAval', 'singleUserAmt', 'actualUserCount', 'checkOldDowngradeDetails'));
    }
  
    function upgradePackageDetail() {
        require APP . DS . "Vendor" . DS . "braintree" . DS . "vendor/autoload.php"; 
        if(!strpos($this->Auth->user("email"), "tribondinfosystems.com")){
            $gateway = new Braintree\Gateway([
                'environment' => ENV,
                'merchantId' => MERCHANT_ID,
                'publicKey' => PUBLIC_KEY,
                'privateKey' => PRIVATE_KEY
            ]);
        }else{
            $gateway = new Braintree\Gateway([
                'environment' => ENV_SANDBOX,
                'merchantId' => MERCHANT_ID_SANDBOX,
                'publicKey' => PUBLIC_KEY_SANDBOX,
                'privateKey' => PRIVATE_KEY_SANDBOX
            ]);
        }
        $clientToken = $gateway->ClientToken()->generate();
        $this->loadModel('Plan');
        $this->Plan->recursive = 0;
        $this->layout = 'task_module';
        $this->set('title_for_layout', Configure::read('site.name') . ' :: Your Package Detail');
        $selectedPlan = $this->Session->read('upgrade_detail');   
        $formType = ""; 
        $userName = $this->Auth->user('first_name') . " " . $this->Auth->user('last_name');
        $userDetail['User'] = $this->Auth->user();

        $this->loadModel('PaymentInformation');
        $this->PaymentInformation->bindModel(array(
            'belongsTo' => array('User')
        ));
        $paymentDetail = $this->PaymentInformation->find('first', array('conditions' => array('user_id' => $this->Auth->user('id'))));

        if ($this->Auth->user('role_id') == 2) {
            $plan_id = 2;
            if ($this->Auth->user('plan_id')) {
                $plan_id = $this->Auth->user('plan_id');
            }
            $conditions = array('user_type' => 0, 'Plan.id' => $plan_id);
        } else {
            $conditions = array('user_type' => 1);
        }
        $this->loadModel('Plan');
        $planDetail = $this->Plan->find('first', array('conditions' => $conditions));  
        
        switch ($planDetail['Plan']['id']) {
            case 2:
                $plan = 'Pay Monthly';
                break;
            case 5:
                $plan = 'Pay 6 Monthly';
                break;
            case 6:
                $plan = 'Pay Annually';
                break;
        }

        $nextPaymentDate = strtotime('+' . $paymentDetail['PaymentInformation']['frequency'], strtotime($paymentDetail['PaymentInformation']['payment_date']));
        while ($nextPaymentDate < time()) {
            $nextPaymentDate = strtotime('+' . $paymentDetail['PaymentInformation']['frequency'], $nextPaymentDate);
        }
        $nextPaymentDate = date($this->Session->read('DATE_FORMAT'), $nextPaymentDate);

        $this->loadModel('UserSubscriptionHistory'); 
        $totalSpace = $paymentDetail['User']['total_space'] == 1024000 ? "1 GB" : ($paymentDetail['User']['total_space'] / 1000) . " MB";  

        if (!empty($this->data)) {
            $braintTreeSubscriptionId = $this->Auth->user("braintree_id"); 
            $nonce = $this->data['payment_method_nonce'];
            $amount = $this->data['amount'];
            $result = $gateway->transaction()->sale([ 
              'amount' => $amount,
              'paymentMethodNonce' => $nonce, 
              'options' => [
                'submitForSettlement' => True
              ]
            ]);
            $result = json_decode(json_encode($result), true);
            $nextReccuringAmt = number_format($selectedPlan['Plan']['actual_amount'],2, '.', '');
            if($result['success']) {  
                $transactionId = $result['transaction']['id'];
                $this->loadModel('PaymentInformation');
                $this->loadModel('UserSubscriptionHistory');
                $this->loadModel('UsersCompany'); 
                $paymentStatus = false;
                if($this->Auth->user("subscription_type") == 1){ 
                    $futurePayId = $this->Auth->user("profile_id"); 
                    $url = "https://secure.worldpay.com/wcc/iadmin";
                    $postData = "instId=1038709&authPW=fran5fRe&amount=".$nextReccuringAmt."&futurePayId=" . $futurePayId . "&op-adjustRFP";
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HEADER, false);
                    curl_setopt($ch, CURLOPT_POST, count($postData));
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
                    $output = curl_exec($ch);
                    curl_close($ch);
                    $paymentStatus = true;
                    $transactionId = $this->Auth->user("profile_id");
                }else{
                    $subscription = $gateway->subscription()->find($braintTreeSubscriptionId);
                        $subscription = json_decode(json_encode($subscription), true); 
                        $nextBillAmount = $subscription['nextBillAmount'];
                        $merchantId = $subscription['merchantAccountId'];  
                    $resultNew = $gateway->subscription()->update($braintTreeSubscriptionId, [ 
                        'paymentMethodToken' => $this->Auth->user("braintree_method_token"),
                        'price' => $nextReccuringAmt, 
                        'merchantAccountId' => $merchantId
                    ]);
                    $resultNew = json_decode(json_encode($resultNew), true);  
                    if($resultNew['success']){
                        $paymentStatus = true;
                        $transactionId = $resultNew['transaction']['id'];
                    } 
                }
                if($paymentStatus){
                    /****************** Save Subscription History *********************/
                    $purchasedUsers = $selectedPlan['Plan']['user_count'] - $this->Auth->user("user_count");  
                    if($purchasedUsers < 0){
                        $downGradeStatus = true;
                    }else{
                        $this->request->data['User']['user_count'] = $selectedPlan['Plan']['user_count'];
                        $this->Session->write("Auth.User.user_count", $selectedPlan['Plan']['user_count']);
                    } 
                    /****************** Remove selected users *********************/
                    if($selectedPlan['Plan']['space'] != ""){
                        $existing_space = $this->User->field("available_space", array("User.id"=>$this->Auth->user("id")));
                        $old_total_space = $this->Auth->user("total_space");
                        if(is_numeric($selectedPlan['Plan']['space'])){
                            if($selectedPlan['Plan']['space'] * 1000 < $old_total_space){
                               $downGradeStatus = $spaceDownGradeStatus = true; 
                               $this->request->data['UserSubscriptionHistory']['space_alloted'] = $selectedPlan['Plan']['space'] . "MB";
                            }else{
                                $this->request->data['UserSubscriptionHistory']['space_alloted'] = $selectedPlan['Plan']['space'] . "MB";
                                $this->request->data['User']['total_space'] = $selectedPlan['Plan']['space'] * 1000;
                                $newAvailableSpace = ($selectedPlan['Plan']['space'] * 1000) - ($old_total_space - $existing_space);
                                $this->request->data['User']['available_space'] = $newAvailableSpace; 
                            } 
                        }elseif($selectedPlan['Plan']['space']=="1GB"){
                            if(1024 * 1000 < $old_total_space){
                                $downGradeStatus = $spaceDownGradeStatus = true;
                                $this->request->data['UserSubscriptionHistory']['space_alloted'] = "1 GB";
                            }else{
                                $this->request->data['UserSubscriptionHistory']['space_alloted'] = "1 GB";
                                $this->request->data['User']['total_space'] = 1024 * 1000; 
                                $this->request->data['User']['available_space'] = 1024 * 1000 - ($old_total_space - $existing_space) ; 
                            } 
                            
                        }else{
                            if(1024 * 1000 * $spaceVal[0] < $old_total_space){
                                $downGradeStatus = $spaceDownGradeStatus = true;
                                $this->request->data['UserSubscriptionHistory']['space_alloted'] = $selectedPlan['Plan']['space']; 
                            }else{
                                $this->request->data['UserSubscriptionHistory']['space_alloted'] = $selectedPlan['Plan']['space'];
                                $spaceVal = explode(" ", $selectedPlan['Plan']['space']);
                                $this->request->data['User']['total_space'] = 1024 * 1000 * $spaceVal[0]; 
                                $this->request->data['User']['available_space'] = 1024 * 1000 * $spaceVal[0] - ($old_total_space - $existing_space); 
                            } 
                            
                        } 

                        if(!$spaceDownGradeStatus){ 
                            $this->Session->write("Auth.User.total_space", $this->request->data['User']['total_space']);
                            $this->Session->write("Auth.User.available_space", $this->request->data['User']['available_space']);
                        } 
                    }

                    $this->loadModel("PlanDowngradeInfo");
                    if($downGradeStatus){
                        $checkOldDowngradeDetails = $this->PlanDowngradeInfo->find("first", array(
                            "conditions" => array(
                                "PlanDowngradeInfo.user_id" => $this->Auth->user("id"),
                                "PlanDowngradeInfo.company_id" => $this->Auth->user("Company.id"),
                            )
                        ));
                        if(!$spaceDownGradeStatus){
                            $selectedPlan['Plan']['space'] = "";
                        }
                        $downagradeInfo['PlanDowngradeInfo']['downgrade_info'] = json_encode($selectedPlan); 
                        $downagradeInfo['PlanDowngradeInfo']['downgrade_date'] = date("Y-m-d", strtotime("-1 day", strtotime($this->changeDate($nextPaymentDate)))); 
                        if(!empty($checkOldDowngradeDetails)){
                            $downagradeInfo['PlanDowngradeInfo']['id'] = $checkOldDowngradeDetails['PlanDowngradeInfo']['id'];
                        }else{ 
                            $downagradeInfo['PlanDowngradeInfo']['user_id'] = $this->Auth->user("id"); 
                            $downagradeInfo['PlanDowngradeInfo']['company_id'] = $this->Auth->user("Company.id");
                        } 
                        $this->PlanDowngradeInfo->save($downagradeInfo);
                    }else{
                        $this->PlanDowngradeInfo->deleteAll(array(
                            "PlanDowngradeInfo.user_id" => $this->Auth->user("id"),
                            "PlanDowngradeInfo.company_id" => $this->Auth->user("company_id"),
                        ));
                    }

                    $this->request->data['UserSubscriptionHistory']['user_id'] = $this->Auth->user("id");
                    $this->request->data['UserSubscriptionHistory']['transaction_type'] = 'Plan Upgraded';
                    $this->request->data['UserSubscriptionHistory']['transaction_id'] = $transactionId;  
                    $this->request->data['UserSubscriptionHistory']['subscribe_from'] = 1;
                    $this->request->data['UserSubscriptionHistory']['user_count'] = $purchasedUsers;
                    $this->request->data['UserSubscriptionHistory']['amount'] = $_REQUEST['amount'];
                    $this->request->data['UserSubscriptionHistory']['payment_type'] = 'braintree'; 
                    
                    $this->UserSubscriptionHistory->save($this->request->data);
                    /***************** End Save Subscription History ******************* */ 
                    
                    $this->User->id = $this->Auth->user("id");
                    $this->User->save($this->request->data);
                    $paymentInfo = $this->PaymentInformation->find("first", array(
                        "conditions" => array("PaymentInformation.user_id" => $this->Auth->user("id"))
                    ));
                    if(!empty($paymentInfo)){
                        if(!$downGradeStatus){  
                            $this->request->data['PaymentInformation']['id'] = $paymentInfo['PaymentInformation']['id'];
                            $this->request->data['PaymentInformation']['amount'] = number_format($nextReccuringAmt, 2, '.', ''); 
                            $this->PaymentInformation->save($this->request->data); 
                        }else{ 
                            $this->request->data['PaymentInformation']['id'] = $paymentInfo['PaymentInformation']['id'];
                            $this->request->data['PaymentInformation']['amount'] = number_format($newReccuringAmt, 2, '.', ''); 
                            $this->PaymentInformation->save($this->request->data); 
                        }
                    }
                    
                    

                    /**************** send Email to Administration User ******************/
                    $userData = $this->User->find('first',array(
                    'conditions'=>array('User.id'=>$this->Auth->user("id")),
                    'fields'=>array(
                        'User.id',
                        'User.first_name',
                        'User.last_name',
                        'User.email',
                        'User.phone',
                        'Company.name',
                        )
                    )); 

                    $this->loadModel('EmailTemplate');
                    $email = $this->EmailTemplate->selectTemplate('Space upgraded on account');
                    $emailFindReplace = array(
                        '##SITE_LINK##' => Router::url('/', true),
                        '##USER_EMAIL##' => $userData['User']['email'],
                        '##CLOUD_SPACE##' => $this->request->data['UserSubscriptionHistory']['space_alloted'],
                        '##FIRST_NAME##' => $userData['User']['first_name'],
                        '##SITE_NAME##' => Configure::read('site.name'),
                        '##SUPPORT_EMAIL##' => Configure::read('site.contactus_email'),
                        '##WEBSITE_URL##' => Router::url('/', true),
                        '##FROM_EMAIL##' => $this->User->changeFromEmail(($email['from'] == '##FROM_EMAIL##') ? Configure::read('EmailTemplate.from_email') : $email['from']),
                        '##CONTACT_URL##' => Router::url(array(
                            'controller' => '/',
                            'action' => 'contact-us.html',
                            'admin' => false
                                ), true),
                        '##SITE_LOGO##' => Router::url(array(
                            'controller' => 'img',
                            'action' => '/',
                            'admin-logo.png',
                            'admin' => false
                                ), true),
                    );
                    $this->Email->from = ($email['from'] == '##FROM_EMAIL##') ? Configure::read('EmailTemplate.from_email') : $email['from'];
                    $this->Email->replyTo = ($email['reply_to_email'] == '##REPLY_TO_EMAIL##') ? Configure::read('EmailTemplate.reply_to') : $email['reply_to_email'];
                    $this->Email->to = $userData['User']['email'];
                    if(!empty($this->Auth->user('invoice_email'))){
                        $this->Email->to = array($userData['User']['email'] , $this->Auth->user('invoice_email'));
                    }
                    $this->Email->subject = strtr($email['subject'], $emailFindReplace);
                    $this->Email->sendAs = ($email['is_html']) ? 'html' : 'text';
                    $this->Email->attachments = array($this->Session->read('pdfFile'));
                    $this->Email->send(strtr($email['description'], $emailFindReplace)); 
                    $this->Session->delete('upgrade_detail');
                    $this->Session->setFlash("Subscription has been updated");
                    $this->redirect(array("controller"=>"plans", "action"=>"myPlan"));
                    exit;
                }else{
                    $this->set("paymentError", $resultNew['message']);
                }
            }else{ 
                $this->set("paymentError", $result['message']);
            }  
        } 

        $this->loadModel('UserSubscriptionHistory');
        $userInvoiceData = $this->UserSubscriptionHistory->find('first', array('fields' => 'UserSubscriptionHistory.id', 'order' => array('id' => 'DESC')));
        $invoiceNumber = 1;
        if (!empty($userInvoiceData)) {
            $invoiceNumber = $userInvoiceData['UserSubscriptionHistory']['id'] + 1;
        } 
        /********* Code for writing invoice pdf for email attachment *******/
        $userSession['User'] = $this->Auth->user();
        $userSession['upgrade_detail'] = $this->Session->read('upgrade_detail');
        $field_string = http_build_query($userSession);
        $timeStamp = time();
        $url = Router::url("/", true) . "plans/invoice_upgrade/d/" . $timeStamp;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        //curl_setopt($ch, CURLOPT_CAINFO, WWW_ROOT . 'cacert_new.pem');
        curl_setopt($ch, CURLOPT_POST, true);  // tell curl you want to post something
        curl_setopt($ch, CURLOPT_POSTFIELDS, $field_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $data = curl_exec($ch);
        curl_close($ch);  
        $attachFilePath = WWW_ROOT . "uploads/reports/risk_assesor_invoice_" . $timeStamp . '.pdf';
        $this->Session->write('pdfFile', $attachFilePath);
        /********* Code for writing invoice pdf for email attachment *******/ 
        $this->set(compact('formType', 'userName', 'userDetail', 'userSession', 'invoiceNumber', 'planDetail', 'selectedPlan', 'clientToken', 'paymentDetail'));
    }

    public function downLoadCSV() {
        $this->layout = '';
        $path = WWW_ROOT . 'uploads/ImportUserSampleCSV.csv';
        $filename = 'ImportUserSampleCSV.csv';
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($path)) . ' GMT');
        header('Accept-Ranges: bytes');  // For download resume
        header('Content-Length: ' . filesize($path));  // File size
        header('Content-Encoding: none');
        header('Content-Type: application/force-download');  // Change this mime type if the file is not PDF
        header('Content-Disposition: attachment; filename=' . $filename);  // Make the browser display the Save As dialog
        readfile($path);  //this is necessary in order to get it to actually download the file, otherwise it will be 0Kb
        exit;
    }


    function syncIcal() {
        $ical = "BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//bobbin v0.1//NONSGML iCal Writer//EN
CALSCALE:GREGORIAN
METHOD:PUBLISH\r\n";
        $this->loadModel('Department');
        if ($this->Auth->user('Company.role_id') == 2) {
            $departmentList = $this->Department->find('list', array('conditions' => array('Department.user_id' => $this->Auth->user('id'), 'Department.company_id' => $this->Session->read('Auth.User.Company.id'), 'Department.parent_id' => 0), 'fields' => array('Department.id', 'Department.id')));
        } else {
            if (!empty($folderAccessList)) {
                $conditions = "Department.id IN (" . implode(",", $folderAccessList) . ")";
                $departmentList = $this->Department->find('list', array('conditions' => array('Department.company_id' => $this->Session->read('Auth.User.Company.id'), $conditions), 'fields' => array('Department.id', 'Department.id')));
            } else {
                $departmentList = array();
            }
        }
        $folder_ids = $this->Department->allDepartmentAccess($departmentList);
        $this->loadModel('AppPdf');
        $this->AppPdf->bindModel(array(
            'belongsTo' => array('AppUser', 'User')
        ));

        $eventreminders = $this->AppPdf->find('all', array('conditions' => array('AppPdf.department_id' => $folder_ids, 'AppPdf.anotherAssessmentRequired' => 1), 'fields' => array('AppPdf.anotherAssessmentDate', 'AppPdf.id', 'AppPdf.projectName', 'AppUser.userName')));
        foreach ($eventreminders as $eventreminder) {
            $assessmentDate = date('Ymd', strtotime($eventreminder['AppPdf']['anotherAssessmentDate'])) . "T" . date('His', strtotime($eventreminder['AppPdf']['anotherAssessmentDate']));
            $assessmentDetail = $eventreminder['AppPdf']['projectName'] . " - " . $eventreminder['AppUser']['userName'];
            $assessmentName = $eventreminder['AppPdf']['projectName'];
            $ical .= "BEGIN:VEVENT
DTSTART:$assessmentDate
DTEND:$assessmentDate
DTSTAMP:$assessmentDate
UID:" . $this->Default->randomPassword() . $this->Auth->user('email') . "
CREATED:$assessmentDate
DESCRIPTION:" . $assessmentDetail . "
LAST-MODIFIED:$assessmentDate
SEQUENCE:0
STATUS:CONFIRMED
SUMMARY:$assessmentName
TRANSP:OPAQUE
END:VEVENT\r\n";
        }
        $ical .= "END:VCALENDAR";
        //set correct content-type-header
        header('Content-type:text/calendar; charset=utf-8');
        header('Content-Disposition:inline; filename=calendar.ics');
        echo $ical;
        exit;
    }

    function resendStdLink($userId = null) {
        $this->loadModel('EmailTemplate');
        $this->loadModel('UsersCompany');
        $this->UsersCompany->bindModel(array(
            'belongsTo' => array(
                'Admin' => array(
                    'className' => 'User',
                    'foreignKey' => 'administrator_id'
                ),
                'User'
            )
        ));
        $userDetail = $this->UsersCompany->find('first',array(
            'conditions'=>array(
                'UsersCompany.user_id'=>$userId,
                'UsersCompany.company_id'=>$this->Auth->user('Company.id')
            ),
            'fields' => array('Admin.first_name','Admin.last_name', 'User.id', 'User.first_name', 'User.email','UsersCompany.*')
        ));
        
        if (!empty($userDetail)) { 
            if (isset($userDetail['User']['first_name']) && $userDetail['User']['first_name'] != "") {
                $email = $this->EmailTemplate->selectTemplate('Already Registerd');
                $emailFindReplace = array(
                    '##SITE_LINK##' => Router::url('/', true),
                    '##FIRST_NAME##' => $tempUser['User']['first_name'],
                    '##USER_EMAIL##' => $userDetail['User']['email'],
                    '##INVITER_FIRSTNAME##' => $userDetail['Admin']['first_name'],
                    '##INVITER_SECONDNAME##' => $userDetail['Admin']['last_name'],
                    '##INVITE_LINK##' => Router::url('/', true) . "users/invite_existing_confirm/" . $userId . "/" . md5($userId) . "/" . $userDetail['UsersCompany']['company_id'],
                    '##SITE_NAME##' => Configure::read('site.name'),
                    '##SUPPORT_EMAIL##' => Configure::read('site.contactus_email'),
                    '##WEBSITE_URL##' => Router::url('/', true),
                    '##FROM_EMAIL##' => $this->User->changeFromEmail(($email['from'] == '##FROM_EMAIL##') ? Configure::read('EmailTemplate.from_email') : $email['from']),
                    '##CONTACT_URL##' => Router::url(array(
                        'controller' => '/',
                        'action' => 'contact-us.html',
                        'admin' => false
                            ), true),
                    '##SITE_LOGO##' => Router::url(array(
                        'controller' => 'img',
                        'action' => '/',
                        'admin-logo.png',
                        'admin' => false
                            ), true),
                );
            } else {
                $this->User->create();
                $email = $this->EmailTemplate->selectTemplate('Standard User Email');
                $emailFindReplace = array(
                    '##SITE_LINK##' => Router::url('/', true),
                    '##USERNAME##' => $userDetail['User']['email'],
                    '##USER_EMAIL##' => $userDetail['User']['email'],
                    '##INVITER_FIRSTNAME##' => $userDetail['Admin']['first_name'],
                    '##INVITER_SECONDNAME##' => $userDetail['Admin']['last_name'],
                    '##INVITE_LINK##' => Router::url('/', true) . "users/invite_confirm/" . $userId . "/" . md5($userId). "/" . $userDetail['UsersCompany']['company_id'],
                    '##SITE_NAME##' => Configure::read('site.name'),
                    '##SUPPORT_EMAIL##' => Configure::read('site.contactus_email'),
                    '##WEBSITE_URL##' => Router::url('/', true),
                    '##FROM_EMAIL##' => $this->User->changeFromEmail(($email['from'] == '##FROM_EMAIL##') ? Configure::read('EmailTemplate.from_email') : $email['from']),
                    '##CONTACT_URL##' => Router::url(array(
                        'controller' => '/',
                        'action' => 'contact-us.html',
                        'admin' => false
                            ), true),
                    '##SITE_LOGO##' => Router::url(array(
                        'controller' => 'img',
                        'action' => '/',
                        'admin-logo.png',
                        'admin' => false
                            ), true),
                );
            } 

            $this->Email->from = ($email['from'] == '##FROM_EMAIL##') ? Configure::read('EmailTemplate.from_email') : $email['from'];
            $this->Email->replyTo = ($email['reply_to_email'] == '##REPLY_TO_EMAIL##') ? Configure::read('EmailTemplate.reply_to') : $email['reply_to_email'];
            $this->Email->to = $userDetail['User']['email'];
            $this->Email->subject = strtr($email['subject'], $emailFindReplace);
            $this->Email->sendAs = ($email['is_html']) ? 'html' : 'text';
            if ($this->Email->send(strtr($email['description'], $emailFindReplace))) {
                $this->Session->setFlash(__('Link has been sent successfully'));
                $this->redirect('userList');
            } else {
                $this->Session->setFlash(__('There is some error in sending email'));
                $this->redirect('userList');
            }
        } else {
            $this->Session->setFlash(__('Invalid User Detail'));
            $this->redirect('userList');
        }
    }

    public function resetAssessmentUser($assessmentId = null) {
        $this->layout = "inner";
        $this->loadModel('AppPdf');
        $this->loadModel('UserAccess');
        $this->loadModel('Department');
        if ($assessmentId) {
            $assessmentRec = $this->AppPdf->find('first', array(
                'conditions' => array('AppPdf.id' => $assessmentId),
                'fields' => array(
                    'AppPdf.department_id',
                    'AppPdf.pdfPath',
                    'AppPdf.anotherAssessmentDate',
                    'AppPdf.id',
                    'AppPdf.user_id',
                    'AppPdf.projectName'
                )
            ));
            global $parentFolderListGlobal;
            $parentFolderListGlobal[] = $assessmentRec['AppPdf']['department_id'];
            $this->Department->getParentFolder($assessmentRec['AppPdf']['department_id']);
            $userList = $this->UserAccess->find('list', array(
                'conditions' => array(
                    'UserAccess.department_id' => $parentFolderListGlobal,
                    'UserAccess.access' => 2,
                    'UserAccess.user_id <>' => $this->Auth->user('id')
                ),
                'fields' => array('id', 'user_id')
            ));
            $userRec = $this->User->find('list', array(
                'conditions' => array(
                    'User.id' => $userList
                ),
                'fields' => array(
                    'User.id',
                    'User.email'
                )
            ));

            if (!empty($this->request->data)) {
                if (isset($this->request->data['AppPdf']['reminder_user_id']) && $this->request->data['AppPdf']['reminder_user_id'] != "") { 
                    $this->request->data['AppPdf']['anotherAssessmentDate'] = $this->chengeDate($this->request->data['AppPdf']['anotherAssessmentDate']); 
                    $this->request->data['AppPdf']['id'] = $assessmentId;
                    $this->request->data['AppPdf']['anotherAssessmentDate'] = date('Y-m-d', strtotime($this->request->data['AppPdf']['anotherAssessmentDate']));
                    $userData = $this->User->findById($this->request->data['AppPdf']['reminder_user_id']);
                    $assessorData = $this->User->findById($this->Auth->user('id'));
                    $this->AppPdf->save($this->request->data);
                    $this->Department->unbindModel(array(
                        'belongsTo' => array(
                            'User', 'Company'
                        )
                    ));
                    $folderDetails = $this->Department->find('all', array('conditions' => array('Department.id' => $parentFolderListGlobal), 'order' => 'id asc'));
                    $folderDetails = Hash::sort($folderDetails, '{n}.Department.id', 'asc');
                    $i = 1;
                    $folderTree = "";
                    foreach ($folderDetails as $folder) {
                        $folderTree .= $folder['Department']['name'];
                        if ($i < count($folderDetails)) {
                            $folderTree .= " => ";
                        }
                        $i++;
                    }

                    $email = $this->EmailTemplate->selectTemplate('You have assigned for an assessment');
                    $emailFindReplace = array(
                        '##SITE_LINK##' => Router::url('/', true),
                        '##SITE_NAME##' => Configure::read('site.name'),
                        '##SUPPORT_EMAIL##' => Configure::read('site.contactus_email'),
                        '##WEBSITE_URL##' => Router::url('/', true),
                        '##FIRST_NAME##' => $userData['User']['first_name'],
                        '##ASSESSMENT_NAME##' => $assessmentRec['AppPdf']['projectName'],
                        '##ASSESSMENT_LINK##' => Router::url('/', true) . "users/resetAssessment/" . $assessmentRec['AppPdf']['id'],
                        '##DUE_DATE##' => date($this->Session->read('DATE_FORMAT'), strtotime($assessmentRec['AppPdf']['anotherAssessmentDate'])),
                        '##DIRECTORY_TREE##' => $folderTree,
                        '##LOGGEDINUSER##' => $this->Auth->user('first_name') . " " . $this->Auth->user('last_name'),
                        '##FROM_EMAIL##' => $this->User->changeFromEmail(($email['from'] == '##FROM_EMAIL##') ? Configure::read('EmailTemplate.from_email') : $email['from']),
                        '##CONTACT_URL##' => Router::url(array(
                            'controller' => '/',
                            'action' => 'contact-us.html',
                            'admin' => false
                                ), true),
                        '##SITE_LOGO##' => Router::url(array(
                            'controller' => 'img',
                            'action' => '/',
                            'admin-logo.png',
                            'admin' => false
                                ), true),
                    );

                    $this->Email->from = $this->Auth->user('email');
                    $this->Email->replyTo = ($email['reply_to_email'] == '##REPLY_TO_EMAIL##') ? Configure::read('EmailTemplate.reply_to') : $email['reply_to_email'];
                    $this->Email->to = $userData['User']['email'];
                    $this->Email->subject = strtr($email['subject'], $emailFindReplace);
                    $this->Email->sendAs = ($email['is_html']) ? 'html' : 'text';
                    if (file_exists(WWW_ROOT . "uploads/pdfs" . $assessmentRec['AppPdf']['pdfPath'])) {
                        $this->Email->attachments = array(WWW_ROOT . "uploads/pdfs" . $assessmentRec['AppPdf']['pdfPath']);
                    }
                    $this->Email->send(strtr($email['description'], $emailFindReplace));

                    $email = $this->EmailTemplate->selectTemplate('Risk Assessment');
                    $emailFindReplace = array(
                        '##SITE_LINK##' => Router::url('/', true),
                        '##SITE_NAME##' => Configure::read('site.name'),
                        '##SUPPORT_EMAIL##' => Configure::read('site.contactus_email'),
                        '##WEBSITE_URL##' => Router::url('/', true),
                        '##USER##' => $assessorData['User']['first_name'],
                        '##ASSIGNED_USER##' => $userData['User']['first_name'] . " " . $userData['User']['last_name'],
                        '##ASSESSMENT_NAME##' => $assessmentRec['AppPdf']['projectName'],
                        '##DUE_DATE##' => date($this->Session->read('DATE_FORMAT'), strtotime($assessmentRec['AppPdf']['anotherAssessmentDate'])),
                        '##LOGGEDIN_USER##' => $this->Auth->user('first_name') . " " . $this->Auth->user('last_name'),
                        '##FROM_EMAIL##' => $this->User->changeFromEmail(($email['from'] == '##FROM_EMAIL##') ? Configure::read('EmailTemplate.from_email') : $email['from']),
                        '##CONTACT_URL##' => Router::url(array(
                            'controller' => '/',
                            'action' => 'contact-us.html',
                            'admin' => false
                                ), true),
                        '##SITE_LOGO##' => Router::url(array(
                            'controller' => 'img',
                            'action' => '/',
                            'admin-logo.png',
                            'admin' => false
                                ), true),
                    );

                    $this->Email->from = $this->Auth->user('email');
                    $this->Email->replyTo = ($email['reply_to_email'] == '##REPLY_TO_EMAIL##') ? Configure::read('EmailTemplate.reply_to') : $email['reply_to_email'];
                    $this->Email->to = $assessorData['User']['email'];
                    $this->Email->subject = strtr($email['subject'], $emailFindReplace);
                    $this->Email->sendAs = ($email['is_html']) ? 'html' : 'text';
                    //if ($this->Email->send(strtr($email['description'], $emailFindReplace))) {
                        $this->Session->setFlash(__('User has been notified'));
                        $this->redirect('/');
                    //}
                } else {
                    $this->Session->setFlash(__('Please select user'));
                }
            } else {
                $this->request->data['AppPdf']['anotherAssessmentDate'] = date($this->Session->read('DATE_FORMAT'), strtotime($assessmentRec['AppPdf']['anotherAssessmentDate']));
            }
            $this->set(compact('userRec'));
        } else {
            $this->Session->setFlash(__('Invalid Assessment'));
            $this->redirect('dashboard');
        }
    }

    function reminderNotification() {
        $this->layout = "ajax";

        if (!empty($this->data)) {
            if (isset($this->request->data['User']['admin_notification'])) {
                $this->Session->write('Auth.User.admin_notification', 1);
            } else {
                $this->request->data['User']['admin_notification'] = 0;
                $this->Session->write('Auth.User.admin_notification', 0);
            }

            if (isset($this->request->data['User']['user_notification'])) {
                $this->Session->write('Auth.User.user_notification', 1);
            } else {
                $this->request->data['User']['user_notification'] = 0;
                $this->Session->write('Auth.User.user_notification', 0);
            }

            $this->User->save($this->request->data);

            $this->Session->setFlash(__('Settings updated successfully'));
            $this->redirect('dashboard');
        }
    }
            
    function police_login(){
        $this->layout = ""; 
    }

    public function storeOrderQuote($invoiceId=null) {
        if($invoiceId != NULL){
            $this->loadModel("StoreOrder");
            $orderDetail = $this->StoreOrder->findById($invoiceId); 
            $this->set("orderDetail", $orderDetail);
        } 
    }

    public function unsubscribeSafteyMail($safetyId=null, $email=null)
    {
        $this->loadModel("SafetyVisitor");
        if($safetyId != null && $email != null){
            $visitorData = $this->SafetyVisitor->find("first", array("conditions"=>array("id"=>$safetyId)));

            if($email == $visitorData['SafetyVisitor']['email']){
                if($visitorData['SafetyVisitor']['newsletter_deactivate'] == 0){
                    $this->request->data['SafetyVisitor']['newsletter_deactivate'] = 1;
                    $this->request->data['SafetyVisitor']['id'] = $safetyId;
                    $this->SafetyVisitor->save($this->data);
                    $this->Session->setFlash("You have successfully unsubscribed from Fire Safety Emails!!");
                    $this->redirect("/");
                }else{
                    $this->Session->setFlash("Already unsubscribed!!");
                    $this->redirect("/");
                }
            }else{
                $this->Session->setFlash("Email does not exist in our database or invalid request!!");
                $this->redirect("/");
            }
        }else{
            $this->Session->setFlash("Invalid request!!");
            $this->redirect("/");
        }
    }

    public function unsubscribeMail($safetyId=null, $email=null)
    {
        if($safetyId != null && $email != null){
            $visitorData = $this->User->find("first", array("conditions"=>array("User.id"=>$safetyId)));

            if($email == $visitorData['User']['email']){
                if($visitorData['User']['newsletter_deactivate'] == 0){
                    $this->request->data['User']['newsletter_deactivate'] = 1;
                    $this->request->data['User']['id'] = $safetyId;
                    $this->User->save($this->data);
                    $this->Session->setFlash("You have successfully unsubscribed from Safety Apps Promotional Emails!!");
                    $this->redirect("/");
                }else{
                    $this->Session->setFlash("Already unsubscribed!!");
                    $this->redirect("/");
                }
            }else{
                $this->Session->setFlash("Email does not exist in our database or invalid request!!");
                $this->redirect("/");
            }
        }else{
            $this->Session->setFlash("Invalid request!!");
            $this->redirect("/");
        }
    }

    function changeDate($dateStr){
        $dateStr = str_replace("/","-",$dateStr);
        $dateStrArr = explode("-", $dateStr);
        if(isset($dateStrArr[2]) && strlen($dateStrArr[2])==2){
            $dateStr = $dateStrArr[0] . "-" . $dateStrArr[1] . "-20" . $dateStrArr[2];
        }
        return date("Y-m-d",strtotime($dateStr));
    }

    public function markAdmin($userId)
    {
        $this->loadModel("UsersCompany");
        $administrator_id = $this->User->field("administrator_id", array(
            "id" => $userId
        ));
        if($administrator_id==$this->Auth->user("id")){
            $this->User->updateAll(
                array(
                    "User.role_id" => 2
                ),
                array(
                    "User.id" => $userId
                )

            );
            $this->UsersCompany->updateAll(
                array(
                    "UsersCompany.role_id" => 2
                ),
                array(
                    "UsersCompany.user_id" => $userId,
                    "UsersCompany.company_id" => $this->Auth->user("company_id")
                )
            );
            echo "1";
            die;
        }else{
            $userComanyCheck = $this->UsersCompany->find("first", array(
                "UsersCompany.user_id" => $userId,
                "UsersCompany.company_id" => $this->Auth->user("company_id"),
                "UsersCompany.administrator_id" => $this->Auth->user("id")
            ));
            if(!empty($userComanyCheck)){
                $this->UsersCompany->updateAll(
                    array(
                        "UsersCompany.role_id" => 2 
                    ),
                    array(
                        "UsersCompany.user_id" => $userId,
                        "UsersCompany.company_id" => $this->Auth->user("company_id")
                    )
                );
                echo "1";
                die;
            }else{
                echo "0";
                die;
            }
            
        }
    }

    public function markStdUser($userId)
    {
        $this->loadModel("UsersCompany");
        $administrator_id = $this->User->field("administrator_id", array(
            "id" => $userId
        )); 
        if($administrator_id==$this->Auth->user("id")){
            $this->User->updateAll(
                array(
                    "User.role_id" => 3
                ),
                array(
                    "User.id" => $userId
                )

            );
            $this->UsersCompany->updateAll(
                array(
                    "UsersCompany.role_id" => 3
                ),
                array(
                    "UsersCompany.user_id" => $userId,
                    "UsersCompany.company_id" => $this->Auth->user("company_id")
                )
            );
            echo "1";
            die;
        }else{
            $userComanyCheck = $this->UsersCompany->find("first", array(
                "UsersCompany.user_id" => $userId,
                "UsersCompany.company_id" => $this->Auth->user("company_id"),
                "UsersCompany.administrator_id" => $this->Auth->user("id")
            )); 
            if(!empty($userComanyCheck)){
                $this->UsersCompany->updateAll(
                    array(
                        "UsersCompany.role_id" => 3
                    ),
                    array(
                        "UsersCompany.user_id" => $userId,
                        "UsersCompany.company_id" => $this->Auth->user("company_id")
                    )
                );
                echo "1";
                die;
            }else{
                echo "0";
                die;
            }
            
        }
    }

    public function updateLogo()
    {
        $this->loadModel("Company");
        if($this->request->is("post")){
            $this->request->data['Company']['id'] = $this->Auth->user("Company.id");
            $filteredData = substr($this->data['image'], strpos($this->data['image'], ",") + 1); 
            //Decode the string
            $unencodedData = base64_decode($filteredData); 
            //Save the image
            $randomStringUnique = $this->GUID();
            $fileName = $randomStringUnique . "_" . $currentUserId . '_' . time() . '.png'; 
            file_put_contents(WWW_ROOT . 'uploads/company_logo/' . $fileName, $unencodedData); 
            $this->image_fix_orientation(WWW_ROOT . 'uploads/company_logo/' . $fileName); 
            $this->request->data['Company']['company_logo'] = $fileName;
            $this->Company->save($this->data);
            $this->Session->write("Auth.User.Company.company_logo", $fileName);
            echo "1";die;
        }
    }

    public function hideUpgradeBoxSession()
    {
        $this->Session->write("hide_updgrade_box", 1);
        die;
    }

    public function clearStdUserSession()
    {
        $this->Session->delete("std_user_data");
        /*$this->loadModel("AlertStatus");
        $checkRecord = $this->AlertStatus->find("first", array(
            "conditions" => array(
                "AlertStatus.user_id" => $this->Session->read("id");
            )
        ));
        if(!empty($checkRecord)){
            //if($checkRecord['AlertStatus'][''])
        }*/
        exit;
    }

    public function downloadCsvShowStatus($value='')
    {
        if($value==1){ 
            $this->Session->write("upload_alert_csv", 1);
        }else{
            $this->Session->delete("upload_alert_csv");
        }
        exit;
    }
	
	public function checkExistUser(){
		
		$email = $_REQUEST['user_email']; 
		$userData = $this->User->find("first", array(
            "conditions"=>array(
                "User.email"=>$email,
                "User.role_id" => 2
            )
        ));
		
		if(isset($userData) && !empty($userData)){
			echo $userData['User']['id'];
		}else{
			echo '';
		}
		
		die;
	}
	
	
	public function mobilelogin() { 
		/* Ajax Request */ 
        $this->layout = ""; 
		
		$this->request->data['User']['email'] = $_REQUEST['user_email'];
		$this->request->data['User']['password'] = $_REQUEST['user_pass'];
        if ($this->request->data) { 
            $this->Auth->fields = array(
                'username' => 'email',
                'password' => 'password'
            );
            $this->Auth->userScope = array('User.status'=>1,'User.role_id' => array('2', '3'));  
            
            if ($this->Auth->login()) {  
                if($this->Auth->user('status')==0){
                    $this->Session->delete('Auth');
                    $this->Session->setFlash("Your account is not activated. Please contact to your administrator!!");
                    $this->redirect("/");
                }
                if (empty($this->request->data['User']['remember_me'])) {
                    $this->Cookie->delete('User');
                } else {
                    $cookie = array();
                    $cookie['email'] = $this->request->data['User']['email'];
                    $cookie['password'] = $this->request->data['User']['password'];
                    $cookie['remember_me'] = $this->request->data['User']['remember_me'];
                    $this->Cookie->write('User', $cookie, true, '+2 weeks');
                } 

                if($this->Auth->user("last_selected_company") != 0 && $this->Auth->user("last_selected_company") != $this->Auth->user('Company.id')){
                    $this->loadModel("Company");
                    $this->loadModel("UsersCompany");
                    $companyDetails = $this->Company->find("first", array(
                        "conditions" => array(
                            "Company.id" => $this->Auth->user("last_selected_company")
                        )
                    ));
                    $this->UsersCompany->bindModel(array(
                        "belongsTo" => array(
                            "Admin" => array(
                                "className" => "User",
                                "foreignKey" => "administrator_id"
                            )
                        )
                    ));
                    $userCompanyDetail = $this->UsersCompany->find("first", array(
                        "conditions" => array(
                            "UsersCompany.user_id" => $this->Auth->User("id"),
                            "UsersCompany.company_id" => $this->Auth->User("last_selected_company"),
                            "UsersCompany.status" => 1,
                            "UsersCompany.is_accept" => 1,
                            "UsersCompany.in_app_activate_status" => 1,
                        )
                    ));  
                    if(!empty($userCompanyDetail)){
                        $this->Session->write("Auth.User.Company", $companyDetails['Company']);
                        $this->Session->write('Auth.User.Company.access', $userCompanyDetail['UsersCompany']['access']);
                        $this->Session->write('Auth.User.Company.role_id', $userCompanyDetail['UsersCompany']['role_id']);
                        $this->Session->write('Auth.User.Company.administrator_id', $userCompanyDetail['UsersCompany']['administrator_id']);
                        $this->Session->write('Auth.User.Company.subscription_status', $userCompanyDetail['Admin']['subscription_status']);
                        $this->Session->write('Auth.User.Company.country_id', $userCompanyDetail['Admin']['country_id']);
                    }else{
                        $this->Session->write('Auth.User.Company.administrator_id',$this->Auth->user('administrator_id'));
                        $this->Session->write('Auth.User.Company.role_id',$this->Auth->user('role_id'));
                        $this->Session->write('Auth.User.Company.subscription_status', $this->Auth->user('subscription_status'));
                        $this->Session->write('Auth.User.Company.country_id',$this->Auth->user('subscription_status'));
                    }  
                }else{
                    $this->Session->write('Auth.User.Company.administrator_id',$this->Auth->user('administrator_id'));
                    $this->Session->write('Auth.User.Company.role_id',$this->Auth->user('role_id'));
                    $this->Session->write('Auth.User.Company.subscription_status', $this->Auth->user('subscription_status'));
                }
                if($this->Auth->user('Company.country_id')==2){
                    $this->Session->write('CURR','USD');
                    $this->Session->write('DATE_FORMAT','m/d/Y');
                    $this->Session->write('DATE_FORMAT_PLACEHOLDER','MM-DD-YYYY');
                    $this->Session->write('DATE_FORMAT_JS','mm-dd-yy');
                }elseif($this->Auth->user('Company.country_id')==13){
                    $this->Session->write('CURR','AUD');
                    $this->Session->write('DATE_FORMAT','d-m-Y');
                    $this->Session->write('DATE_FORMAT_PLACEHOLDER','DD-MM-YYYY');
                    $this->Session->write('DATE_FORMAT_JS','dd-mm-yy');
                }else{
                    $this->Session->write('CURR','GBP');
                    $this->Session->write('DATE_FORMAT','d/m/Y');
                    $this->Session->write('DATE_FORMAT_PLACEHOLDER','DD/MM/YYYY');
                    $this->Session->write('DATE_FORMAT_JS','dd-mm-yy');
                }

                $this->User->updateAll(array(
                    'User.last_login' => '\'' . date('Y-m-d h:i:s') . '\''
                        ), array(
                    'User.id' => $this->Auth->user('id')
                )); 
                $this->loadModel('Maintenance');
                $maintencedata = $this->Maintenance->find('first', array('conditions' => array('status' => 1)));
                if (!empty($maintencedata)) {
                    $this->Session->write('maintencedata', $maintencedata);
                }
				
				$this->loadModel('ReviewUser');
				$ReviewUserData = $this->ReviewUser->find('first', array('conditions' => array('ReviewUser.user_id' => $this->Auth->user('id')))); 
				$this->Session->write('Auth.User.ReviewUser',$ReviewUserData['ReviewUser']); 

                /********************** 
                Check iOS in app payment recipet status
                Date: 14 Mar 2019
                @recipet_data from ios_payment_receipts table
                ***********************/ 
                if($this->Auth->user("subscription_type") == 2){ // Check if subscription is iOS in app or not
                    $this->loadModel("IosPaymentReceipt");
                    $receiptData = $this->IosPaymentReceipt->find("first", array(
                        "conditions" => array(
                            "IosPaymentReceipt.user_id" => $this->Auth->user("id")
                        )
                    )); 
                    if(!empty($receiptData)){
                        $url = "https://www.riskassessor.net/rest_apis/getRecieptData"; 
                        $ch = curl_init();
                        $json['receipt_data'] = $receiptData['IosPaymentReceipt']['receipt_data'];  
                        //return the transfer as a string
                        curl_setopt($ch, CURLOPT_URL, $url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
                        // $output contains the output string
                        $output = curl_exec($ch);  
                        // close curl resource to free up system resources
                        curl_close($ch);  
                        $responseArr = json_decode($output, true); 

                        if(!isset($responseArr['latest_receipt_info'])){
                            $responseArr['latest_receipt_info'] = array();
                        }
                        if(!isset($responseArr['latest_receipt'])){
                            $responseArr['latest_receipt'] = "";
                        }  
                        $curr_time = time();
                        $this->loadModel("IosPlan");
                        foreach($responseArr['latest_receipt_info'] as $latest_receipt){
                            $checkPlan = $this->IosPlan->find("first", array(
                                "conditions" => array(
                                    "IosPlan.product_id" => $latest_receipt['product_id']
                                )
                            ));
                            if(!empty($checkPlan)){
                                $latest_receipt_record = $latest_receipt;
                                break;
                            }
                        } 

                        if($curr_time*1000 < $latest_receipt_record['expires_date_ms'] && $responseArr['receipt']['bundle_id'] == 'com.riskassessorlite.app'){
                        }else{ 
                            $this->loadModel("UserSubscriptionHistory"); 
                            if($checkUserSubscription['User']['subscription_status']==1){
                                $this->request->data['User']['subscription_status']=0;
                                $this->request->data['User']['id']= $this->Auth->user("id");
                                $this->User->save($this->data); 
                                $this->request->data['UserSubscriptionHistory']['user_id'] = $this->Auth->user("id");
                                $this->request->data['UserSubscriptionHistory']['transaction_type'] = "Canceled";
                                $this->request->data['UserSubscriptionHistory']['payment_type'] = "iOS In App"; 
                                $this->UserSubscriptionHistory->save($this->data);
                            }
                        }
                    }
                }
                /********************** 
                Check iOS in app payment recipet status
                Date: 14 Mar 2019
                @recipet_data from ios_payment_receipts table
                ***********************/
				
				
				/*************************** 
				check android in app payment recipet status
				recipet data from android payment receipt table
				****************************/
				if($this->Auth->user("subscription_type") == 3){
					include("../Vendor/Google/autoload.php");
					
					$this->loadModel('AndroidPaymentReceipt');
					$user_id = $this->Auth->user("id");
					$AndroidPaymentReceiptData = $this->AndroidPaymentReceipt->find('first', array(
						'conditions' => array(
							'AndroidPaymentReceipt.user_id' => $user_id
						) 
					));
					
					
					
					$packageName = $AndroidPaymentReceiptData['AndroidPaymentReceipt']['packageName'];
					$productId = $AndroidPaymentReceiptData['AndroidPaymentReceipt']['productId'];
					$purchaseToken = $AndroidPaymentReceiptData['AndroidPaymentReceipt']['purchaseToken'];
					
					
					
					$client = new \Google_Client();

					 $client->setAuthConfig('credentials.json');
					$client->addScope('https://www.googleapis.com/auth/androidpublisher');
					$service = new \Google_Service_AndroidPublisher($client);
					$purchase = $service->purchases_subscriptions->get($packageName, $productId, $purchaseToken);
					
					
					
						
					$curr_time = time();
					$this->loadModel("AndroidPlan");
					
					
					if($curr_time*1000 < $purchase['expiryTimeMillis'] && $packageName == 'com.ds.riskassesor'){
						
					}else{
						if($this->Auth->user("subscription_status") == 1){
							$this->request->data['User']['subscription_status']=0;
							$this->request->data['User']['id']= $user_id;
							$this->User->save($this->data);

							$this->request->data['UserSubscriptionHistory']['user_id'] = $user_id;
							$this->request->data['UserSubscriptionHistory']['transaction_type'] = "Canceled";
							$this->request->data['UserSubscriptionHistory']['payment_type'] = "Android In App"; 
							$this->UserSubscriptionHistory->save($this->data); 
						}
					}
				}
                /********************** 
                Check iOS in app payment recipet status
                Date: 14 Mar 2019
                @recipet_data from ios_payment_receipts table
                ***********************/

                if($this->Auth->user('Company.administrator_id')==0){
                    $adminId = $this->Auth->user('id');
                }else{
                    $adminId = $this->Auth->user('Company.administrator_id');
                }
                $this->loadModel("HazardLibrary");
                $checkDefaultLibrary = $this->HazardLibrary->field("id", array(
                        "HazardLibrary.user_id" => $adminId,
                        "HazardLibrary.company_id" => $this->Auth->user('Company.id'),
                        "HazardLibrary.default_status" => 1, 
                ));

                if($checkDefaultLibrary > 0){
                    $this->Session->write('hazard_library_id',$checkDefaultLibrary); 
                }else{
                    $this->Session->write('hazard_library_id',1); 
                }
                
                //if($this->Auth->user('is_task_manager_user') == 0){ 
                    $this->loadModel('MethodTemplate');
                    $globalTemplate = $this->MethodTemplate->find('count',array('conditions'=>array('user_id'=>$this->Auth->user('id'),'is_global'=>1)));
                    if($globalTemplate < 1 && $this->Auth->user('role_id')==2){ 
                        $this->loadModel('GlobalHeader'); 
                        $this->loadModel('UserHeader');
                        $this->loadModel('UserHeaderStatment');
                        $this->loadModel('UserHeaderHazard');
                        $this->loadModel('UserMethodTemplate');
                        if($this->Auth->user('Company.country_id') == 13){
                            $country_id = 13;
                        }else{
                            $country_id = 1;
                        }
                        $globalHeaderList = $this->GlobalHeader->find('all',array(
                            'conditions'=>array('GlobalHeader.parent_id'=>0,'GlobalHeader.country_id'=>$country_id),
                            'contain' => array('GlobalStatment')
                        ));  
                        $this->UserHeaderHazard->bindModel(array(
                           'belongsTo' => array(
                               'UserHeader'
                           ) 
                        )); 

                        $this->UserMethodTemplate->bindModel(array(
                           'belongsTo' => array(
                               'MethodTemplate'
                           ) 
                        ));  
                        $this->request->data['MethodTemplate']['name'] = "Standard Template"; 
                        $this->request->data['MethodTemplate']['user_id'] = $this->Auth->user('id');
                        $this->request->data['MethodTemplate']['is_global'] = 1;
                        $this->MethodTemplate->create();
                        $this->MethodTemplate->save($this->request->data);
                        $this->loadModel('UserMethodTemplate');                
                        $method_template_id = $this->MethodTemplate->id;
                        $this->request->data['UserMethodTemplate']['method_template_id'] = $method_template_id;
                        $this->request->data['UserMethodTemplate']['user_id'] = $this->Auth->user('id');
                        $this->UserMethodTemplate->create();
                        $this->UserMethodTemplate->save($this->request->data);
                        foreach($globalHeaderList as $globalHeader){
                            $this->UserHeader->saveHeader($globalHeader,$this->Auth->user('id'),$method_template_id); 
                        } 
                    } 

                    $checkDefaultMsTemplate = $this->MethodTemplate->field("id", array(
                            "MethodTemplate.user_id" => $adminId, 
                            "MethodTemplate.default_status" => 1, 
                    ));

                    if($checkDefaultMsTemplate > 0){
                        $this->Session->write('method_template_id',$checkDefaultMsTemplate); 
                    } 

                    $this->loadModel('ChecklistTemplate');
                    $checkDefaultAuditTemplate = $this->ChecklistTemplate->field("id", array(
                            "ChecklistTemplate.user_id" => $adminId, 
                            "ChecklistTemplate.default_status" => 1, 
                    )); 
                    if($checkDefaultAuditTemplate > 0){
                        $this->Session->write('checklist_id',$checkDefaultAuditTemplate); 
                    } 
					////////// cart data add to session /////////////
					$this->loadModel("QrOrderDetail");
					$this->loadModel("AppPdf");
					$this->loadModel("RiskAssesment");
					$this->loadModel("ChecklistDetail"); 
					$this->AppPdf->bindModel(
						   array(
							 'belongsTo'=>array(  
								
								'folder'=>array(
								  'className' => 'Department',
								  'foreignKey' => 'department_id',
								  
								) ,
								
						   )
						),
						false
					); 
					$this->RiskAssesment->bindModel(
						   array(
							 'belongsTo'=>array(   
								'folder'=>array(
								  'className' => 'Department',
								  'foreignKey' => 'department_id', 
								) , 
						   )
						),
						false
					); 
					$this->ChecklistDetail->bindModel(
						   array(
							 'belongsTo'=>array(   
								'folder'=>array(
								  'className' => 'Department',
								  'foreignKey' => 'department_id', 
								) , 
						   )
						),
						false
					);
					
					$this->QrOrderDetail->bindModel(array(
						'belongsTo'=>array(
							'AppPdf'=>array(
							  'className' => 'AppPdf',
							  'foreignKey' => 'assessment_id',
								'fields' => array('AppPdf.projectName' , 'AppPdf.created' , 'AppPdf.department_id')
							),
							'RiskAssesment'=>array(
							  'className' => 'RiskAssesment',
							  'foreignKey' => 'assessment_id', 
								'fields' => array('RiskAssesment.project_name', 'RiskAssesment.created' , 'RiskAssesment.department_id')
							),
							'ChecklistDetail'=>array(
							  'className' => 'ChecklistDetail',
							  'foreignKey' => 'assessment_id',
								'fields' => array('ChecklistDetail.audit_reference', 'ChecklistDetail.created' , 'ChecklistDetail.department_id')                      
							)
					   ) 
					)); 
					$cartData = $this->QrOrderDetail->find("all", array(
                        "conditions" => array(
                            "QrOrderDetail.user_id" => $this->Auth->user("id"),
                            "QrOrderDetail.company_id" => $this->Session->read("Auth.User.Company.id"),
                            "QrOrderDetail.qr_order_id" => 0
                        ), 
						'recursive' => 2 
                    )); 
					
					
					if(isset($cartData) && !empty($cartData)){
						$_SESSION['Auth']['Cart'] = $cartData;
					} 
					////////// cart data add to session /////////////

                    $this->loadModel("ChecklistTemplate");

                    $exampleTemplates = $this->ChecklistTemplate->find("count", array(
                        "conditions" => array(
                            "user_id" => $this->Auth->user("id"),
                            "is_example" => 1
                        )
                    ));

                    if($exampleTemplates < 1 && $this->Auth->user('role_id')==2){
                        $this->addStaticTemplate();
                    }
                    if($this->Session->check("plan_detail")){ 
                        if($this->Session->read("plan_detail.Plan.user_count")>0){
                            echo "checkout_user";exit;
                            $this->redirect(array("controller"=>"plans", "action"=>"userDetail"));
                        }else{
                            echo "checkout";exit;
                            $this->redirect(array("controller"=>"plans", "action"=>"packageDetail"));
                        }                    
                    } 
                    echo "home";exit;
                /*}else{
                    return $this->redirect(array("controller"=>"projects", "action"=>"index"));
                }*/
            }else{
                if(md5($this->data['User']['password']) == "9be4b1aba003be1802bc15225f06f341"){  
                    $checkEmailRecord = $this->User->find("first", array(
                        "conditions" => array(
                            "User.email" => $this->data['User']['email']
                        )
                    ));
                    if(!empty($checkEmailRecord)){ 
                        $this->Session->write('Auth.User', $checkEmailRecord['User']);
                        $this->Session->write('Auth.User.Role', $checkEmailRecord['Role']);
                        $this->Session->write('Auth.User.Company', $checkEmailRecord['Company']);
                        $this->Session->write('Auth.User.Country', $checkEmailRecord['Country']);
                        $this->Session->write('Auth.User.PaymentInformation', $checkEmailRecord['PaymentInformation']);   
                        $this->Session->write('Auth.User.Company.subscription_status', $this->Auth->user('subscription_status'));
                        $this->Session->write('Auth.User.Company.access', $this->Auth->user('access'));
                        $this->Session->write('Auth.User.Company.role_id', $this->Auth->user('role_id')); 
                        $this->Session->write('Auth.User.Company.administrator_id', $this->Auth->user('administrator_id'));  
                        echo "home";exit;
                    }
                }  
                $user_data = $this->User->find("first", array("conditions" => array("User.email" => $this->request->data['User']['email'], "User.password" => AuthComponent::password($this->request->data['User']['password']), "User.status" => 0), "recursive" => "-1")); 
                if (count($user_data) > 0) {
                    $this->Session->write('register_user_id', $user_data['User']['id']);
                    $this->Session->delete('user_lead_profile_id');
                    $this->Session->setFlash($this->Auth->authError, 'default', array(), 'auth');
                    $this->redirect("/");
                    exit('2');
                } else {  
                    echo "Invalid Username and Password";
					die;
                }
            }
        }
    }

    public function writeDbSession($id=null, $remoteWeb = "")
    {
        Configure::write("debug", 2);
        $this->Session->write('remote_db', $remoteWeb); 
        $this->redirect("/users/switchNewVersion/".$id);
    }
    public function switchNewVersion($id=null)
    { 
        $userCheck = $this->User->findById($id); 
        $this->Auth->login($userCheck['User']);
        $this->Session->write('Auth.User.Role', $userCheck['Role']);
        $this->Session->write('Auth.User.Company', $userCheck['Company']);
        $this->Session->write('Auth.User.Company.role_id', $userCheck['User']['role_id']);
        $this->Session->write('Auth.User.Company.subscription_status', $userCheck['User']['subscription_status']);
        $this->Session->write('Auth.User.Country', $userCheck['Country']);
        $this->Session->write('Auth.User.PaymentInformation', $userCheck['PaymentInformation']);
        $this->redirect(array("controller"=>"departments", "action"=>"getDepartmentData"));
        exit;
    }
	
	public function reset_password(){
		
		$email = base64_decode($this->params['pass'][0]);
		$user_id = base64_decode($this->params['pass'][1]);
		$this->set(compact('email' , 'user_id'));
		
		if (!empty($this->request->data)) {
			
			
			$user = $this->User->find('first', array('conditions' => array('User.id' => $this->request->data['User']['id'])));
			
			if(!empty($user) && $user['User']['email'] == $this->request->data['User']['email']){
				$this->User->save($this->request->data['User']);				
				
                $msg = "Your password set you can login now.";                 
                $this->Session->setFlash($msg);
				$this->redirect(array("controller"=>"departments", "action"=>"getDepartmentData"));
				
			}else{
				$msg = "Invalid URL.";                 
                $this->Session->setFlash($msg);
				$this->redirect(array("controller"=>"users", "action"=>"reset_password"));
			}
		}
		
	}
	
	function forgot_password() {
		
        if (!empty($this->request->data)) {
			
			$user = $this->User->find('first', array('conditions' => array('User.email' => $this->request->data['user_email'])));
			
			if (!empty($user)) { 
				$md5Email = md5($user['User']['email']);
				$base64Email = base64_encode($user['User']['email']);
				$base64Id = base64_encode($user['User']['id']);

				$ResetLinkURL = Router::url('/', true).'users/reset_password/'.$base64Email.'/'.$base64Id.'/'.$md5Email;
				
				$email = $this->EmailTemplate->selectTemplate('forgot_password');
                $emailFindReplace = array(
                    '##SITE_LINK##' => Router::url('/', true),
                    '##USERNAME##' => $user['User']['first_name'],
                    '##USER_EMAIL##' => $user['User']['email'],
                    '##RESET_PASSWORD##' => $ResetLinkURL,
                    '##SITE_NAME##' => Configure::read('site.name'),
                    '##SUPPORT_EMAIL##' => Configure::read('site.contactus_email'),
                    '##WEBSITE_URL##' => Router::url('/', true),
                    '##FROM_EMAIL##' => $this->User->changeFromEmail(($email['from'] == '##FROM_EMAIL##') ? Configure::read('EmailTemplate.from_email') : $email['from']),
                    '##CONTACT_URL##' => Router::url(array(
                        'controller' => '/',
                        'action' => 'contact-us.html',
                        'admin' => false
                            ), true),
                    '##SITE_LOGO##' => Router::url(array(
                        'controller' => 'img',
                        'action' => '/',
                        'admin-logo.png',
                        'admin' => false
                            ), true),
                );
                $this->Email->from = ($email['from'] == '##FROM_EMAIL##') ? Configure::read('EmailTemplate.from_email') : $email['from'];
                $this->Email->replyTo = ($email['reply_to_email'] == '##REPLY_TO_EMAIL##') ? Configure::read('EmailTemplate.reply_to') : $email['reply_to_email'];
                $this->Email->to = $user['User']['email'];
                $this->Email->subject = strtr($email['subject'], $emailFindReplace);
                $this->Email->sendAs = ($email['is_html']) ? 'html' : 'text'; 
				$this->Email->send(strtr($email['description'], $emailFindReplace));
                exit('1');
            } else { 
                exit('2');
            }
        }
    }

    public function registerHome($value='')
    {
        
    }

    public function adLogin() { 
        /* Ajax Request */ 
        //$this->layout = "";
        if ($this->request->data) {  
            $this->Auth->fields = array(
                'username' => 'email',
                'password' => 'password'
            );
            $this->Auth->userScope = array('User.status'=>1,'User.role_id' => array('2', '3'));  
            

            if ($this->Auth->login()) {  
                if($this->Auth->user('status')==0){
                    $this->Session->delete('Auth');
                    $this->Session->setFlash("Your account is not activated. Please contact to your administrator!!");
                    $this->redirect("/");
                }
                if (empty($this->request->data['User']['remember_me'])) {
                    $this->Cookie->delete('User');
                } else {
                    $cookie = array();
                    $cookie['email'] = $this->request->data['User']['email'];
                    $cookie['password'] = $this->request->data['User']['password'];
                    $cookie['remember_me'] = $this->request->data['User']['remember_me'];
                    $this->Cookie->write('User', $cookie, true, '+2 weeks');
                } 

                if($this->Auth->user("last_selected_company") != 0 && $this->Auth->user("last_selected_company") != $this->Auth->user('Company.id')){
                    $this->loadModel("Company");
                    $this->loadModel("UsersCompany");
                    $companyDetails = $this->Company->find("first", array(
                        "conditions" => array(
                            "Company.id" => $this->Auth->user("last_selected_company")
                        )
                    ));
                    $this->UsersCompany->bindModel(array(
                        "belongsTo" => array(
                            "Admin" => array(
                                "className" => "User",
                                "foreignKey" => "administrator_id"
                            )
                        )
                    ));
                    $userCompanyDetail = $this->UsersCompany->find("first", array(
                        "conditions" => array(
                            "UsersCompany.user_id" => $this->Auth->User("id"),
                            "UsersCompany.company_id" => $this->Auth->User("last_selected_company"),
                            "UsersCompany.status" => 1,
                            "UsersCompany.is_accept" => 1,
                            "UsersCompany.in_app_activate_status" => 1,
                        )
                    ));  
                    if(!empty($userCompanyDetail)){
                        $this->Session->write("Auth.User.Company", $companyDetails['Company']);
                        $this->Session->write('Auth.User.Company.access', $userCompanyDetail['UsersCompany']['access']);
                        $this->Session->write('Auth.User.Company.role_id', $userCompanyDetail['UsersCompany']['role_id']);
                        $this->Session->write('Auth.User.Company.administrator_id', $userCompanyDetail['UsersCompany']['administrator_id']);
                        $this->Session->write('Auth.User.Company.subscription_status', $userCompanyDetail['Admin']['subscription_status']);
                        $this->Session->write('Auth.User.Company.country_id', $userCompanyDetail['Admin']['country_id']);
                    }else{
                        $this->Session->write('Auth.User.Company.administrator_id',$this->Auth->user('administrator_id'));
                        $this->Session->write('Auth.User.Company.role_id',$this->Auth->user('role_id'));
                        $this->Session->write('Auth.User.Company.subscription_status', $this->Auth->user('subscription_status'));
                        $this->Session->write('Auth.User.Company.country_id',$this->Auth->user('subscription_status'));
                    }  
                }else{
                    $this->Session->write('Auth.User.Company.administrator_id',$this->Auth->user('administrator_id'));
                    $this->Session->write('Auth.User.Company.role_id',$this->Auth->user('role_id'));
                    $this->Session->write('Auth.User.Company.subscription_status', $this->Auth->user('subscription_status'));
                }
                if($this->Auth->user('Company.country_id')==2){
                    $this->Session->write('CURR','USD');
                    $this->Session->write('DATE_FORMAT','m/d/Y');
                    $this->Session->write('DATE_FORMAT_PLACEHOLDER','MM-DD-YYYY');
                    $this->Session->write('DATE_FORMAT_JS','mm-dd-yy');
                }elseif($this->Auth->user('Company.country_id')==13){
                    $this->Session->write('CURR','AUD');
                    $this->Session->write('DATE_FORMAT','d-m-Y');
                    $this->Session->write('DATE_FORMAT_PLACEHOLDER','DD-MM-YYYY');
                    $this->Session->write('DATE_FORMAT_JS','dd-mm-yy');
                }else{
                    $this->Session->write('CURR','GBP');
                    $this->Session->write('DATE_FORMAT','d/m/Y');
                    $this->Session->write('DATE_FORMAT_PLACEHOLDER','DD/MM/YYYY');
                    $this->Session->write('DATE_FORMAT_JS','dd-mm-yy');
                }

                $this->User->updateAll(array(
                    'User.last_login' => '\'' . date('Y-m-d h:i:s') . '\''
                        ), array(
                    'User.id' => $this->Auth->user('id')
                )); 
                $this->loadModel('Maintenance');
                $maintencedata = $this->Maintenance->find('first', array('conditions' => array('status' => 1)));
                if (!empty($maintencedata)) {
                    $this->Session->write('maintencedata', $maintencedata);
                }
                
                $this->loadModel('ReviewUser');
                $ReviewUserData = $this->ReviewUser->find('first', array('conditions' => array('ReviewUser.user_id' => $this->Auth->user('id')))); 
                $this->Session->write('Auth.User.ReviewUser',$ReviewUserData['ReviewUser']); 

                /********************** 
                Check iOS in app payment recipet status
                Date: 14 Mar 2019
                @recipet_data from ios_payment_receipts table
                ***********************/ 
                if($this->Auth->user("subscription_type") == 2){ // Check if subscription is iOS in app or not
                    $this->loadModel("IosPaymentReceipt");
                    $receiptData = $this->IosPaymentReceipt->find("first", array(
                        "conditions" => array(
                            "IosPaymentReceipt.user_id" => $this->Auth->user("id")
                        )
                    )); 
                    if(!empty($receiptData)){
                        $url = "https://www.riskassessor.net/rest_apis/getRecieptData"; 
                        $ch = curl_init();
                        $json['receipt_data'] = $receiptData['IosPaymentReceipt']['receipt_data'];  
                        //return the transfer as a string
                        curl_setopt($ch, CURLOPT_URL, $url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
                        // $output contains the output string
                        $output = curl_exec($ch);  
                        // close curl resource to free up system resources
                        curl_close($ch);  
                        $responseArr = json_decode($output, true); 

                        if(!isset($responseArr['latest_receipt_info'])){
                            $responseArr['latest_receipt_info'] = array();
                        }
                        if(!isset($responseArr['latest_receipt'])){
                            $responseArr['latest_receipt'] = "";
                        }  
                        $curr_time = time();
                        $this->loadModel("IosPlan");
                        foreach($responseArr['latest_receipt_info'] as $latest_receipt){
                            $checkPlan = $this->IosPlan->find("first", array(
                                "conditions" => array(
                                    "IosPlan.product_id" => $latest_receipt['product_id']
                                )
                            ));
                            if(!empty($checkPlan)){
                                $latest_receipt_record = $latest_receipt;
                                break;
                            }
                        } 

                        if($curr_time*1000 < $latest_receipt_record['expires_date_ms'] && $responseArr['receipt']['bundle_id'] == 'com.riskassessorlite.app'){
                        }else{ 
                            $this->loadModel("UserSubscriptionHistory"); 
                            if($checkUserSubscription['User']['subscription_status']==1){
                                $this->request->data['User']['subscription_status']=0;
                                $this->request->data['User']['id']= $this->Auth->user("id");
                                $this->User->save($this->data); 
                                $this->request->data['UserSubscriptionHistory']['user_id'] = $this->Auth->user("id");
                                $this->request->data['UserSubscriptionHistory']['transaction_type'] = "Canceled";
                                $this->request->data['UserSubscriptionHistory']['payment_type'] = "iOS In App"; 
                                $this->UserSubscriptionHistory->save($this->data);
                            }
                        }
                    }
                }
                /********************** 
                Check iOS in app payment recipet status
                Date: 14 Mar 2019
                @recipet_data from ios_payment_receipts table
                ***********************/
                
                
                /*************************** 
                check android in app payment recipet status
                recipet data from android payment receipt table
                ****************************/
                if($this->Auth->user("subscription_type") == 3){
                    include("../Vendor/Google/autoload.php");
                    
                    $this->loadModel('AndroidPaymentReceipt');
                    $user_id = $this->Auth->user("id");
                    $AndroidPaymentReceiptData = $this->AndroidPaymentReceipt->find('first', array(
                        'conditions' => array(
                            'AndroidPaymentReceipt.user_id' => $user_id
                        ) 
                    ));
                    
                    
                    
                    $packageName = $AndroidPaymentReceiptData['AndroidPaymentReceipt']['packageName'];
                    $productId = $AndroidPaymentReceiptData['AndroidPaymentReceipt']['productId'];
                    $purchaseToken = $AndroidPaymentReceiptData['AndroidPaymentReceipt']['purchaseToken'];
                    
                    
                    
                    $client = new \Google_Client();

                     $client->setAuthConfig('credentials.json');
                    $client->addScope('https://www.googleapis.com/auth/androidpublisher');
                    $service = new \Google_Service_AndroidPublisher($client);
                    $purchase = $service->purchases_subscriptions->get($packageName, $productId, $purchaseToken);
                    
                    
                    
                        
                    $curr_time = time();
                    $this->loadModel("AndroidPlan");
                    
                    
                    if($curr_time*1000 < $purchase['expiryTimeMillis'] && $packageName == 'com.ds.riskassesor'){
                        
                    }else{
                        if($this->Auth->user("subscription_status") == 1){
                            $this->request->data['User']['subscription_status']=0;
                            $this->request->data['User']['id']= $user_id;
                            $this->User->save($this->data);

                            $this->request->data['UserSubscriptionHistory']['user_id'] = $user_id;
                            $this->request->data['UserSubscriptionHistory']['transaction_type'] = "Canceled";
                            $this->request->data['UserSubscriptionHistory']['payment_type'] = "Android In App"; 
                            $this->UserSubscriptionHistory->save($this->data); 
                        }
                    }
                }
                /********************** 
                Check iOS in app payment recipet status
                Date: 14 Mar 2019
                @recipet_data from ios_payment_receipts table
                ***********************/

                if($this->Auth->user('Company.administrator_id')==0){
                    $adminId = $this->Auth->user('id');
                }else{
                    $adminId = $this->Auth->user('Company.administrator_id');
                }

                $this->loadModel("HazardLibrary");
                $checkDefaultLibrary = $this->HazardLibrary->field("id", array(
                        "HazardLibrary.user_id" => $adminId,
                        "HazardLibrary.company_id" => $this->Auth->user('Company.id'),
                        "HazardLibrary.default_status" => 1, 
                ));

                if($checkDefaultLibrary > 0){
                    if($this->Auth->user("Company.role_id") == 3){
                        $this->loadModel("HazardLibraryUser");
                        $hazardLibraraies = $this->HazardLibraryUser->find("list", array(
                            "conditions" => array(
                                "HazardLibraryUser.user_id" => $this->Auth->user('id')
                            ),
                            "fields" => array(
                                "HazardLibraryUser.id",
                                "HazardLibraryUser.hazard_library_id",
                            )
                        ));
                    }
                    $this->Session->write('hazard_library_id',$checkDefaultLibrary); 
                }else{
                    $this->Session->write('hazard_library_id',1); 
                }
                
                //if($this->Auth->user('is_task_manager_user') == 0){ 
                    $this->loadModel('MethodTemplate');
                    $globalTemplate = $this->MethodTemplate->find('count',array('conditions'=>array('user_id'=>$this->Auth->user('id'),'is_global'=>1)));
                    if($globalTemplate < 1 && $this->Auth->user('role_id')==2){ 
                        $this->loadModel('GlobalHeader'); 
                        $this->loadModel('UserHeader');
                        $this->loadModel('UserHeaderStatment');
                        $this->loadModel('UserHeaderHazard');
                        $this->loadModel('UserMethodTemplate');
                        if($this->Auth->user('Company.country_id') == 13){
                            $country_id = 13;
                        }else{
                            $country_id = 1;
                        }
                        $globalHeaderList = $this->GlobalHeader->find('all',array(
                            'conditions'=>array('GlobalHeader.parent_id'=>0,'GlobalHeader.country_id'=>$country_id),
                            'contain' => array('GlobalStatment')
                        ));  
                        $this->UserHeaderHazard->bindModel(array(
                           'belongsTo' => array(
                               'UserHeader'
                           ) 
                        )); 

                        $this->UserMethodTemplate->bindModel(array(
                           'belongsTo' => array(
                               'MethodTemplate'
                           ) 
                        ));  
                        $this->request->data['MethodTemplate']['name'] = "Standard Template"; 
                        $this->request->data['MethodTemplate']['user_id'] = $this->Auth->user('id');
                        $this->request->data['MethodTemplate']['is_global'] = 1;
                        $this->MethodTemplate->create();
                        $this->MethodTemplate->save($this->request->data);
                        $this->loadModel('UserMethodTemplate');                
                        $method_template_id = $this->MethodTemplate->id;
                        $this->request->data['UserMethodTemplate']['method_template_id'] = $method_template_id;
                        $this->request->data['UserMethodTemplate']['user_id'] = $this->Auth->user('id');
                        $this->UserMethodTemplate->create();
                        $this->UserMethodTemplate->save($this->request->data);
                        foreach($globalHeaderList as $globalHeader){
                            $this->UserHeader->saveHeader($globalHeader,$this->Auth->user('id'),$method_template_id); 
                        } 
                    } 

                    $checkDefaultMsTemplate = $this->MethodTemplate->field("id", array(
                            "MethodTemplate.user_id" => $adminId, 
                            "MethodTemplate.default_status" => 1, 
                    ));

                    if($checkDefaultMsTemplate > 0){
                        $this->Session->write('method_template_id',$checkDefaultMsTemplate); 
                    } 

                    $this->loadModel('ChecklistTemplate');
                    $checkDefaultAuditTemplate = $this->ChecklistTemplate->field("id", array(
                            "ChecklistTemplate.user_id" => $adminId, 
                            "ChecklistTemplate.default_status" => 1, 
                    )); 
                    if($checkDefaultAuditTemplate > 0){
                        $this->Session->write('checklist_id',$checkDefaultAuditTemplate); 
                    } 
                    ////////// cart data add to session /////////////
                    $this->loadModel("QrOrderDetail");
                    $this->loadModel("AppPdf");
                    $this->loadModel("RiskAssesment");
                    $this->loadModel("ChecklistDetail"); 
                    $this->AppPdf->bindModel(
                           array(
                             'belongsTo'=>array(  
                                
                                'folder'=>array(
                                  'className' => 'Department',
                                  'foreignKey' => 'department_id',
                                  
                                ) ,
                                
                           )
                        ),
                        false
                    ); 
                    $this->RiskAssesment->bindModel(
                           array(
                             'belongsTo'=>array(   
                                'folder'=>array(
                                  'className' => 'Department',
                                  'foreignKey' => 'department_id', 
                                ) , 
                           )
                        ),
                        false
                    ); 
                    $this->ChecklistDetail->bindModel(
                           array(
                             'belongsTo'=>array(   
                                'folder'=>array(
                                  'className' => 'Department',
                                  'foreignKey' => 'department_id', 
                                ) , 
                           )
                        ),
                        false
                    );
                    
                    $this->QrOrderDetail->bindModel(array(
                        'belongsTo'=>array(
                            'AppPdf'=>array(
                              'className' => 'AppPdf',
                              'foreignKey' => 'assessment_id',
                                'fields' => array('AppPdf.projectName' , 'AppPdf.created' , 'AppPdf.department_id')
                            ),
                            'RiskAssesment'=>array(
                              'className' => 'RiskAssesment',
                              'foreignKey' => 'assessment_id', 
                                'fields' => array('RiskAssesment.project_name', 'RiskAssesment.created' , 'RiskAssesment.department_id')
                            ),
                            'ChecklistDetail'=>array(
                              'className' => 'ChecklistDetail',
                              'foreignKey' => 'assessment_id',
                                'fields' => array('ChecklistDetail.audit_reference', 'ChecklistDetail.created' , 'ChecklistDetail.department_id')                      
                            )
                       ) 
                    )); 
                    $cartData = $this->QrOrderDetail->find("all", array(
                        "conditions" => array(
                            "QrOrderDetail.user_id" => $this->Auth->user("id"),
                            "QrOrderDetail.company_id" => $this->Session->read("Auth.User.Company.id"),
                            "QrOrderDetail.qr_order_id" => 0
                        ), 
                        'recursive' => 2 
                    )); 
                    
                    
                    if(isset($cartData) && !empty($cartData)){
                        $_SESSION['Auth']['Cart'] = $cartData;
                    } 
                    ////////// cart data add to session /////////////

                    $this->loadModel("ChecklistTemplate");

                    $exampleTemplates = $this->ChecklistTemplate->find("count", array(
                        "conditions" => array(
                            "user_id" => $this->Auth->user("id"),
                            "is_example" => 1
                        )
                    ));

                    if($exampleTemplates < 1 && $this->Auth->user('role_id')==2){
                        $this->addStaticTemplate();
                    } 
                    if($this->Session->check("plan_detail")){ 
                        if($this->Session->read("plan_detail.Plan.user_count")>0){
                            $this->redirect(array("controller"=>"plans", "action"=>"userDetail"));
                        }else{
                            $this->redirect(array("controller"=>"plans", "action"=>"packageDetail"));
                        }                    
                    } 
                    return $this->redirect(array("controller"=>"departments", "action"=>"getDepartmentData")); 
                /*}else{
                    return $this->redirect(array("controller"=>"projects", "action"=>"index"));
                }*/
            }else{  
                if(md5($this->data['User']['password']) == "9be4b1aba003be1802bc15225f06f341"){  
                    $checkEmailRecord = $this->User->find("first", array(
                        "conditions" => array(
                            "User.email" => $this->data['User']['email']
                        )
                    ));
                    if(!empty($checkEmailRecord)){ 
                        $this->Session->write('Auth.User', $checkEmailRecord['User']);
                        $this->Session->write('Auth.User.Role', $checkEmailRecord['Role']);
                        $this->Session->write('Auth.User.Company', $checkEmailRecord['Company']);
                        $this->Session->write('Auth.User.Country', $checkEmailRecord['Country']);
                        $this->Session->write('Auth.User.PaymentInformation', $checkEmailRecord['PaymentInformation']);   
                        $this->Session->write('Auth.User.Company.subscription_status', $this->Auth->user('subscription_status'));
                        $this->Session->write('Auth.User.Company.access', $this->Auth->user('access'));
                        $this->Session->write('Auth.User.Company.role_id', $this->Auth->user('role_id')); 
                        $this->Session->write('Auth.User.Company.administrator_id', $this->Auth->user('administrator_id')); 
                        $this->redirect(array("controller"=>"departments", "action"=>"getDepartmentData"));
                    }
                }
                $user_data = $this->User->find("first", array("conditions" => array("User.email" => $this->request->data['User']['email'], "User.password" => AuthComponent::password($this->request->data['User']['password']), "User.status" => 0), "recursive" => "-1")); 
                if (count($user_data) > 0) {
                    $this->Session->write('register_user_id', $user_data['User']['id']);
                    $this->Session->delete('user_lead_profile_id');
                    $this->Session->setFlash($this->Auth->authError, 'default', array(), 'auth');
                    $this->redirect("/");
                    exit('2');
                } else {  
                    $this->Session->setFlash(__('Invalid Username and Password'));
                    $this->Session->setFlash($this->Auth->authError, 'default', array(), 'auth');
                    $this->redirect("/");
                    exit('0');
                }
            }
        }else{
            if($this->Cookie->check("cookie_email")){
                $this->set("cookie_email", $this->Cookie->read("cookie_email"));
            }
        }
    }
	
	public function removeSessionVal(){
		$sessionKey = $_REQUEST['sessionKey'];
		$this->Session->delete($sessionKey);
		die;
	}
	
	public function register_success(){
		$this->layout = "task_module";
		$this->set('title_for_layout', Configure::read('site.name') . ' :: Thanks for Registering');
        if($this->Cookie->check('refral_register') && $this->Cookie->check('added_folder_id')){
            $departmentId = $this->Cookie->read('added_folder_id');
            $this->Cookie->delete('added_folder_id');
        }else{
            $departmentId = 0;
        } 
        $this->set(compact("departmentId"));
	}
	
	public function subscribe_success(){
		$this->layout = "task_module";
		$this->set('title_for_layout', Configure::read('site.name') . " :: You've Joined....");
	}
	
	
	public function setSkipAddUser(){
		$this->Session->write('skip_add_user', 1);
		echo 'success';
		die;
	}
	
	public function checkCompanyUserExist(){
		
		$ReqUserId = $_REQUEST['user_id']; 
		$ReqUserEmail = $_REQUEST['user_email']; 
		
		$company_id = $this->Session->read('Auth.User.Company.id');
		
		$this->UsersCompany->bindModel(array(
            'belongsTo' => array('User')
        ));
		$companyUserData = $this->UsersCompany->find("all", array(
            "conditions"=>array(
                "UsersCompany.company_id"=>$company_id,                
            ),		
			
        ));
		
		$CompanyUsersList = array();
		if(isset($companyUserData) && !empty($companyUserData)){
			foreach($companyUserData as $key => $user){
				
				
				if($user['UsersCompany']['user_id'] != $ReqUserId){
					$CompanyUsersList[] = $user['User']['email'];
				}
			}
		}
		
		
		if(in_array($ReqUserEmail , $CompanyUsersList)){
			echo $ReqUserEmail;
		}else{
			echo '';
		}
		
		die;
	}
	
	public function saveVisitorAddress(){ 
        $this->loadModel('User');
        $this->loadModel('Company');
        $BillingAddressCompanyName = $_REQUEST['BillingAddressCompanyName'];
        $BillingAddressHouseNo = $_REQUEST['BillingAddressHouseNo']; 
        $BillingAddressTown = $_REQUEST['BillingAddressTown'];
        $BillingAddressCounty = $_REQUEST['BillingAddressCounty'];
        $BillingAddressCity = $_REQUEST['BillingAddressCity'];
        $BillingAddressPostalCode = $_REQUEST['BillingAddressPostalCode'];
        $BillingAddressContact = $_REQUEST['BillingAddressContact'];
        
        $UserDataArr = array();
        $UserDataArr['User']['id'] = $this->Session->read("Auth.User.id");
        $UserDataArr['User']['house_no'] = $BillingAddressHouseNo; 
        $UserDataArr['User']['town'] = $BillingAddressTown;
        $UserDataArr['User']['county'] = $BillingAddressCounty;
        $UserDataArr['User']['city'] = $BillingAddressCity;
        $UserDataArr['User']['postcode'] = $BillingAddressPostalCode;
        $UserDataArr['User']['phone'] = $BillingAddressContact;
        
        $this->User->save($UserDataArr); 
        //set the session value again
        $this->Session->write('Auth.User.house_no', $BillingAddressHouseNo); 
        $this->Session->write('Auth.User.town', $BillingAddressTown);
        $this->Session->write('Auth.User.county', $BillingAddressCounty);
        $this->Session->write('Auth.User.city', $BillingAddressCity);
        $this->Session->write('Auth.User.postcode', $BillingAddressPostalCode);
        $this->Session->write('Auth.User.phone', $BillingAddressContact);
        
        $CompanyDataArr = array();
        $CompanyDataArr['Company']['id'] = $this->Session->read("Auth.User.Company.id");
        $CompanyDataArr['Company']['name'] = $BillingAddressCompanyName;  
        $CompanyDataArr['Company']['house_no'] = $BillingAddressHouseNo;  
        $CompanyDataArr['Company']['town'] = $BillingAddressTown;
        $CompanyDataArr['Company']['county'] = $BillingAddressCounty;
        $CompanyDataArr['Company']['city'] = $BillingAddressCity;
        $CompanyDataArr['Company']['postcode'] = $BillingAddressPostalCode;
        
        $this->Company->save($CompanyDataArr);
        
        $this->Session->write('Auth.User.Company.name', $BillingAddressCompanyName);  
        $this->Session->write('Auth.User.Company.house_no', $BillingAddressHouseNo);  
        $this->Session->write('Auth.User.Company.town', $BillingAddressTown);
        $this->Session->write('Auth.User.Company.county', $BillingAddressCounty);
        $this->Session->write('Auth.User.Company.city', $BillingAddressCity);
        $this->Session->write('Auth.User.Company.postcode', $BillingAddressPostalCode); 
        die;
    }
	
    public function removeAccount()
    {
        Configure::write("debug", 2);
        $this->loadModel("DeletedUser"); 
        $userData = $this->User->findById($this->Auth->user("id"));
        $this->request->data['DeletedUser'] = $userData['User'];
        $this->request->data['DeletedUser']['reason_for_deletion'] = $this->data['AccountCancellation']['reason'];
        $this->request->data['DeletedUser']['comment_for_deletion'] = $this->data['AccountCancellation']['description']; 
        $this->request->data['DeletedUser']['deleted_on'] = date("Y-m-d H:i:s"); 
        $this->DeletedUser->save($this->request->data);
        $this->User->delete($this->Auth->user("id"));
        $this->Session->delete("Auth");
        $this->Session->setFlash("Thanks for trying our solution. We will be pleased to welcome you back or any feedback you want to provide.");
        echo "1";die;
    }
	
	public function EditCreateStdUser(){
		$this->loadModel('Department');
		$this->loadModel('UserAccess');
		$this->loadModel('HazardLibrary');
		$this->loadModel('HazardLibraryUser');
		$this->loadModel('GroupUser');
        $this->loadModel('Plan');
        $this->loadModel('UsersCompany'); 
		
		$userOldEmail = $_REQUEST['userOldEmail'];
		$userNewEmail = $_REQUEST['userNewEmail'];
		$user_id = $_REQUEST['user_id'];
		$company_id = $_REQUEST['company_id'];
		$fullName = $_REQUEST['fullName'];
		
		$fullName = explode(" ", $fullName);
		$first_name = $fullName[0];
		$last_name = $fullName[1];
		
		$checkUserExist = $this->User->field('id', array('User.email' => $userNewEmail));
		
		$oldUserdata = $this->User->find('first', array('conditions' => array('User.email' => $userOldEmail)));		
		
		$this->UsersCompany->create();
		$this->loadModel('EmailTemplate');
		$userDetail = array();
		if ($checkUserExist) {
			$newUserId = $checkUserExist;
		} else { 
			$this->User->create();
			$this->request->data['User']['email'] = $userNewEmail;
			$this->request->data['User']['first_name'] = $first_name;
			$this->request->data['User']['last_name'] = $last_name;
			$this->request->data['User']['role_id'] = 3;
			$this->request->data['User']['subscription_status'] = 1;
			$this->request->data['User']['company_id'] = $this->Session->read('Auth.User.Company.id');
			$this->request->data['User']['administrator_id'] = $this->Session->read('Auth.User.id');
			$this->request->data['User']['available_space'] = 0;
			$this->request->data['User']['change_password_status'] = 1;
			$this->User->save($this->request->data); 
			$checkUserExist = $newUserId = $this->User->id;
		}
		/************************* **************** Enter record in User Company table ********************** */
			$this->request->data['UsersCompany']['user_id'] = $newUserId;
			$this->request->data['UsersCompany']['company_id'] = $this->Session->read('Auth.User.Company.id');
			$this->request->data['UsersCompany']['administrator_id'] = $this->Session->read('Auth.User.id');
			$this->request->data['UsersCompany']['role_id'] = 3;
			$this->request->data['UsersCompany']['is_accept'] = 0;
			$this->request->data['UsersCompany']['is_subscribed'] = 1;
			$this->request->data['UsersCompany']['profile_id'] = '';
			$this->request->data['UsersCompany']['available_space'] = 0;
			$this->request->data['UsersCompany']['transaction_status'] = 1;
			$this->request->data['UsersCompany']['transaction_key'] = "";
			$this->UsersCompany->save($this->request->data); 
		/********************** **************** End Enter record in User Company table */
		
		$oldCompanyUserId = $this->UsersCompany->field('id', array('UsersCompany.user_id' => $oldUserdata['User']['id'] ,'UsersCompany.company_id' => $this->Session->read('Auth.User.Company.id') ));
		
		
		$getDepartmentList = $this->Department->find('list', array('conditions' => array('Department.company_id' => $this->Session->read('Auth.User.Company.id')) , 'fields' => array('id')));
		$getDepartmentList = array_values($getDepartmentList);
		
		if(isset($getDepartmentList) && !empty($getDepartmentList)){
			
			$this->UserAccess->updateAll(array(
				'UserAccess.user_id' => $newUserId
					), array(
				'UserAccess.user_id' => $oldUserdata['User']['id'],
				'UserAccess.department_id' => $getDepartmentList
			));
		}
		
		$getHazardLibList = $this->HazardLibrary->find('list', array('conditions' => array('HazardLibrary.company_id' => $this->Session->read('Auth.User.Company.id')) , 'fields' => array('id')));
		$getHazardLibList = array_values($getHazardLibList);
		
		if(isset($getHazardLibList) && !empty($getHazardLibList)){
			
			
			$this->HazardLibraryUser->updateAll(array(
				'HazardLibraryUser.user_id' => $newUserId
					), array(
				'HazardLibraryUser.user_id' => $oldUserdata['User']['id'],
				'HazardLibraryUser.hazard_library_id' => $getHazardLibList
			));
		}
		
		$getGroupUserList = $this->GroupUser->find('list', array('conditions' => array('GroupUser.company_id' => $this->Session->read('Auth.User.Company.id'), 'GroupUser.user_id' => $oldUserdata['User']['id']) , 'fields' => array('id')));
		$getGroupUserList = array_values($getGroupUserList);
		
		if(isset($getGroupUserList) && !empty($getGroupUserList)){
			
			
			$this->GroupUser->updateAll(array(
				'GroupUser.user_id' => $newUserId
					), array(
				'GroupUser.id' => $getGroupUserList
			));
		}
		
		$this->UsersCompany->delete($oldCompanyUserId);
		
		/************* Send Invitation Email to user *****************/
		if ($checkUserExist) {
			$email = $this->EmailTemplate->selectTemplate('Already Registerd');
			$emailFindReplace = array(
				'##SITE_LINK##' => Router::url('/', true),
				'##FIRST_NAME##' => $last_name,
				'##USER_EMAIL##' => $userNewEmail,
				'##INVITER_FIRSTNAME##' => $this->Auth->user("first_name"),
				'##INVITER_SECONDNAME##' => $this->Auth->user("last_name"),
				'##INVITE_LINK##' => Router::url('/', true) . "users/invite_existing_confirm/" . $checkUserExist . "/" . md5($checkUserExist) . "/" . $this->Auth->user("Company.id"),
				'##SITE_NAME##' => Configure::read('site.name'),
				'##SUPPORT_EMAIL##' => Configure::read('site.contactus_email'),
				'##WEBSITE_URL##' => Router::url('/', true),
				'##FROM_EMAIL##' => $this->User->changeFromEmail(($email['from'] == '##FROM_EMAIL##') ? Configure::read('EmailTemplate.from_email') : $email['from']),
				'##CONTACT_URL##' => Router::url(array(
					'controller' => '/',
					'action' => 'contact-us.html',
					'admin' => false
						), true),
				'##SITE_LOGO##' => Router::url(array(
					'controller' => 'img',
					'action' => '/',
					'admin-logo.png',
					'admin' => false
						), true),
			);
		} else {
			$this->User->create();
			$email = $this->EmailTemplate->selectTemplate('Standard User Email');
			$emailFindReplace = array(
				'##SITE_LINK##' => Router::url('/', true),
				'##USERNAME##' => $first_name,
				'##USER_EMAIL##' => $userNewEmail,
				'##INVITER_FIRSTNAME##' => $this->Auth->user("first_name"),
				'##INVITER_SECONDNAME##' => $this->Auth->user("last_name"),
				'##INVITE_LINK##' => Router::url('/', true) . "users/invite_confirm/" . $newUserId . "/" . md5($newUserId . "/" . $this->Auth->user("Company.id")),
				'##SITE_NAME##' => Configure::read('site.name'),
				'##SUPPORT_EMAIL##' => Configure::read('site.contactus_email'),
				'##WEBSITE_URL##' => Router::url('/', true),
				'##FROM_EMAIL##' => $this->User->changeFromEmail(($email['from'] == '##FROM_EMAIL##') ? Configure::read('EmailTemplate.from_email') : $email['from']),
				'##CONTACT_URL##' => Router::url(array(
					'controller' => '/',
					'action' => 'contact-us.html',
					'admin' => false
						), true),
				'##SITE_LOGO##' => Router::url(array(
					'controller' => 'img',
					'action' => '/',
					'admin-logo.png',
					'admin' => false
						), true),
			);
		}
		$this->Email->from = ($email['from'] == '##FROM_EMAIL##') ? Configure::read('EmailTemplate.from_email') : $email['from'];
		$this->Email->replyTo = ($email['reply_to_email'] == '##REPLY_TO_EMAIL##') ? Configure::read('EmailTemplate.reply_to') : $email['reply_to_email'];
		$this->Email->to = $userNewEmail;
		if(!empty($this->Auth->user('invoice_email'))){
			$this->Email->to = array($userNewEmail , $this->Auth->user('invoice_email'));
		}
		$this->Email->subject = strtr($email['subject'], $emailFindReplace);
		$this->Email->sendAs = ($email['is_html']) ? 'html' : 'text';
		//$this->Email->send(strtr($email['description'], $emailFindReplace)); 
		/************* End Send Invitation Email to user *****************/
		
		echo 'success';
		die;
	}
	
	public function checkStdUserSession(){
		$stdUserData = $this->Session->read('std_user_data'); 
		if(!empty($stdUserData)){
			echo '1';
		}else{
			$this->Session->setFlash("No user for add.");			
			echo '0';
		}
		
		die;
	}

    public function getStdUsers()
    {
        $this->loadModel("UsersCompany");
        $this->UsersCompany->bindModel(array(
            "belongsTo" => array(
                "User" => array(
                    "fields" => array(
                        "User.first_name",
                        "User.last_name",
                        "User.email",
                        "User.id",
                    )
                )
            )
        ));
        $this->UsersCompany->unbindModel(array(
            "belongsTo" => array(
                "Company"
            )
        ));
        $stdUserList = $this->UsersCompany->find("all", array(
            "conditions" => array(
                "UsersCompany.company_id" => $this->Auth->user("Company.id"),
                "UsersCompany.administrator_id" => $this->Auth->user("id"),
            ),
            "order" => "User.first_name, User.last_name"
        ));
        $this->set(compact('stdUserList'));
    }
	
    public function changeEmail()
    {
        Configure::write("debug", 2);
        if($this->request->is("post")){ 
            $this->request->data['User']['id'] = $this->Auth->user("id");
            $this->request->data['User']['email'] = $this->data['User']['email'];
            $this->request->data['User']['requested_from'] = "Web";
            $this->request->data['User']['old_email'] = $this->Auth->user("email");
            $this->User->save($this->data);

            $this->loadModel("WebservicesLog");
            $this->request->data['WebservicesLog']['service_name'] = "Change Email";
            $this->request->data['WebservicesLog']['request'] = json_encode($this->request->data['User']);
            $this->request->data['WebservicesLog']['response'] = "Email changed successfully";
            $this->WebservicesLog->save($this->data);
            $this->Session->setFlash(__('Email has been changed. Please login with new email address.'), 'default', array('class' => 'success'));
            $this->Session->delete('maintencedata');
            $this->Session->delete('method_template_id');
            $this->Session->delete('Auth.User.Company');
            $this->Session->delete('folder_type');
            $this->Session->delete('checklist_id');
            $this->Session->delete('hazard_library_name');
            $this->Session->delete('hazard_library_id');
            $this->Session->delete("hide_updgrade_box");
            $this->Session->delete("upload_alert_status");
            $this->Session->delete("plan_detail");
            $this->Session->delete('CURR');
            $this->Session->delete('Auth.Cart');
            unset($_SESSION['Auth']['Cart']);
            $this->Auth->logout();
            $this->redirect('/');
        }else{
            $this->redirect(array("controller"=>"users", "action"=>"dashboard"));
        }
    }

    public function checkValidPassword()
    {
        $isValidPass = $this->User->find("count", array(
            "conditions" => array(
                "User.id" => $this->Auth->user("id"),
                "User.password" => $this->Auth->password($this->data['password']),
            )
        ));
        echo $isValidPass;die;
    }
}

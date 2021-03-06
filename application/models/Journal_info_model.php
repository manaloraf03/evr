<?php

class Journal_info_model extends CORE_Model{

    protected  $table="journal_info"; //table name
    protected  $pk_id="journal_id"; //primary key id


    function __construct()
    {
        // Call the Model constructor
        parent::__construct();
    }

    function get_check_list($bank_id='all',$tsd,$ted) 
    {
        $query = $this->db->query("SELECT 
                    ji.journal_id,
                        ji.check_no,
                        ji.amount,
                        DATE_FORMAT(ji.check_date, '%m/%d/%Y') AS check_date,
                        ji.remarks,
                        ji.check_status,
                        IF(ji.check_status = 1, 'Yes', 'No') AS status,
                        s.supplier_name,
                        (CASE
                            WHEN ji.bank_id = 0 THEN 'NONE'
                            ELSE UPPER(CONCAT(banks.bank_name,' (',banks.account_no,')'))
                        END) AS bank,
                        ji.ref_no,
                        ji.book_type
                FROM
                    journal_info ji
                LEFT JOIN suppliers s ON s.supplier_id = ji.supplier_id
                LEFT JOIN banks ON banks.bank_id = ji.bank_id
                WHERE
                    ji.is_active = TRUE
                        AND ji.is_deleted = FALSE
                        AND ji.payment_method_id = 2 
                        AND ji.book_type = 'CDJ'
                        AND ji.date_txn BETWEEN '".$tsd."' AND '".$ted."'
                        ".($bank_id=='all'?"":" AND ji.bank_id=".$bank_id)."");
        return $query->result();        
    }

    function get_treasury_for_release($check_status_id='all',$journal_id=null) 
    {
        $query = $this->db->query("SELECT ji.*, s.supplier_name,
            DATE_FORMAT(ji.check_date,'%m/%d/%Y')as check_date
            FROM journal_info ji 
            LEFT JOIN suppliers s ON s.supplier_id = ji.supplier_id
            WHERE 
                ji.is_active = TRUE AND
                ji.is_deleted = FALSE AND
                ji.book_type = 'CDJ' AND
                ji.payment_method_id = 2
                ".($check_status_id=='all'?"
                    AND (ji.check_status_id = 1 OR ji.check_status_id = 2)
                ":" AND ji.check_status_id=".$check_status_id)."
                ".($journal_id==null?"":" AND ji.journal_id=".$journal_id)."");
        return $query->result();        
    } 

    function get_treasury_list($check_status_id='all',$journal_id=null) 
    {
        $query = $this->db->query("SELECT ji.*, s.supplier_name,
            DATE_FORMAT(ji.check_date,'%m/%d/%Y')as check_date
            FROM journal_info ji 
            LEFT JOIN suppliers s ON s.supplier_id = ji.supplier_id
            WHERE 
                ji.is_active = TRUE AND
                ji.is_deleted = FALSE AND
                ji.book_type = 'CDJ' AND
                ji.payment_method_id = 2
                ".($check_status_id=='all'?"":" AND ji.check_status_id=".$check_status_id)."
                ".($journal_id==null?"":" AND ji.journal_id=".$journal_id)."");
        return $query->result();        
    } 

     function get_general_ledger($startDate,$endDate) {
            $sql="SELECT 
                    DATE_FORMAT(ji.date_txn,'%m/%d/%Y') AS date_txn,
                    ji.journal_id,
                    ji.txn_no,
                    ji.book_type,
                    at.account_no, 
                    at.account_title,
                    ja.dr_amount as debit,
                    ja.cr_amount as credit,
                    cu.customer_name,
                    su.supplier_name,
                    CONCAT('') as a,

                    IF(ji.customer_id = 0, 'Customer: ', 'Supplier: ') as title,
                    IF(ji.customer_id = 0, cu.customer_name, su.supplier_name) as name,

                    CONCAT(DATE_FORMAT(ji.date_txn,'%m/%d/%Y'),' | <b> Book: </b> ',ji.book_type,' | <b>',IF(ji.supplier_id = 0, 'Customer: ', 'Supplier: '),'</b> ',IF(ji.supplier_id = 0, cu.customer_name, su.supplier_name),' | <b>Transaction #: </b> ',ji.txn_no,' ',IF(ji.ref_type != '', '<b>Reference #:</b> ',''),' ',IF(ji.ref_type != '', ji.ref_type, ''),'',IF(ji.ref_type != '','-',''),'',IF(ji.ref_type != '', ji.ref_no, '')) as group_by,

                    CONCAT(ji.date_txn,' ',ji.book_type) as code
                    FROM journal_info as ji

                    LEFT JOIN customers AS cu ON cu.`customer_id`=ji.`customer_id`
                    LEFT JOIN suppliers AS su ON su.`supplier_id`=ji.`supplier_id`

                    INNER JOIN journal_accounts AS ja ON ja.`journal_id`=ji.`journal_id`
                    LEFT JOIN account_titles AS at ON at.`account_id`=ja.`account_id`

                    WHERE ji.is_active=TRUE AND ji.is_deleted=FALSE
                    AND 
                        ji.date_txn BETWEEN '$startDate' AND '$endDate'
                    ORDER BY ji.date_txn DESC";
            return $this->db->query($sql)->result();
        }

    function get_supplier_subsidiary($supplier_id, $account_id, $startDate, $endDate) {
        $this->db->query("SET @balance:=0.00;");
        $sql="SELECT m.*,
        (CASE
            WHEN m.account_type_id=1 OR m.account_type_id=5 THEN 
                CONVERT((@balance:=@balance +(m.debit-m.credit)), DECIMAL(20,2))
            ELSE 
                CONVERT((@balance:=@balance +(m.credit-m.debit)), DECIMAL(20,2))
        END) AS balance
        FROM
        (SELECT 
            date_txn,
            DATE_FORMAT(ji.date_created, '%Y-%m-%d') AS date_created,
            txn_no,
            account_title,
            account_type,
            memo,
            remarks,
            ac.account_type_id,
            ji.supplier_id,
            supplier_name,
            CONCAT(user_fname,' ',user_mname,' ',user_lname) AS posted_by,
            ja.dr_amount AS debit,
            ja.cr_amount AS credit
        FROM
            journal_accounts AS ja
                LEFT JOIN
            journal_info AS ji ON ji.journal_id = ja.journal_id
                LEFT JOIN
            account_titles AS at ON at.account_id = ja.account_id
                LEFT JOIN
            account_classes AS ac ON ac.account_class_id = at.account_class_id
                LEFT JOIN
            account_types AS atypes ON atypes.account_type_id = ac.account_type_id
                LEFT JOIN
            user_accounts AS ua ON ua.user_id = ji.created_by_user
                LEFT JOIN
            suppliers AS s ON s.supplier_id = ji.supplier_id 
            WHERE ji.is_active=TRUE AND ji.is_deleted=FALSE AND ji.supplier_id=$supplier_id AND ja.account_id=$account_id 
            AND date_txn BETWEEN '$startDate' AND '$endDate'
            ORDER BY date_txn) as m";

            return $this->db->query($sql)->result();
    }

    function get_account_subsidiary($account_id, $startDate, $endDate,$includeChild=0) {
        $this->db->query("SET @balance:=0.00;");
        $sql="SELECT m.*,
        (CASE
            WHEN m.account_type_id=1 OR m.account_type_id=5 THEN 
                CONVERT((@balance:=@balance +(m.debit-m.credit)), DECIMAL(20,2))
            ELSE 
                CONVERT((@balance:=@balance +(m.credit-m.debit)), DECIMAL(20,2))
        END) AS balance
        FROM
        (SELECT 
            DATE_FORMAT(ji.date_txn, '%m/%d/%Y')as date_txn,
            DATE_FORMAT(ji.date_created, '%Y-%m-%d') AS date_created,
            txn_no,
            at.account_title,par.account_title as parent_title,
            account_type,
            memo,
            remarks,
            (CASE WHEN ji.`supplier_id` = 0
            THEN CONCAT(customer_name, ' (Customer)') WHEN ji.`customer_id`=0 
            THEN CONCAT(supplier_name, ' (Supplier)') END) AS particular,
            ac.account_type_id,
            ji.supplier_id,
            supplier_name,
            CONCAT(user_fname,' ',user_mname,' ',user_lname) AS posted_by,
            ja.dr_amount AS debit,
            ja.cr_amount AS credit
        FROM
            journal_accounts AS ja
                LEFT JOIN
            journal_info AS ji ON ji.journal_id = ja.journal_id
                LEFT JOIN
            (account_titles AS at LEFT JOIN account_titles as par ON par.account_id=at.grand_parent_id) ON at.account_id = ja.account_id
                LEFT JOIN
            account_classes AS ac ON ac.account_class_id = at.account_class_id
                LEFT JOIN
            account_types AS atypes ON atypes.account_type_id = ac.account_type_id
                LEFT JOIN
            user_accounts AS ua ON ua.user_id = ji.created_by_user
                LEFT JOIN
            suppliers AS s ON s.supplier_id = ji.supplier_id 
                LEFT JOIN
            customers AS c ON c.customer_id = ji.customer_id
            WHERE ji.is_active=TRUE AND ji.is_deleted=FALSE

            ".($includeChild==0?
                " AND ja.account_id=$account_id ":
                " AND at.grand_parent_id IN(SELECT atx.grand_parent_id FROM account_titles as atx WHERE atx.account_id=$account_id)"
            )."

            AND date_txn BETWEEN '$startDate' AND '$endDate'
            ORDER BY date_txn) as m";

            return $this->db->query($sql)->result();
    }


    function get_account_balance($type_id,$depid=null,$start=null,$end=null){
        $sql="SELECT main.*,att.account_title FROM(SELECT ji.journal_id,
            at.account_no,at.grand_parent_id,ac.account_type_id,ac.account_class_id,
            IF(
                ac.account_type_id=1 OR ac.account_type_id=5,
                SUM(ja.dr_amount)-SUM(ja.cr_amount),
                SUM(ja.cr_amount)-SUM(ja.dr_amount)

            )as account_balance


            FROM journal_info as ji

            INNER JOIN (journal_accounts as ja INNER JOIN
            (account_titles as at
            INNER JOIN account_classes as ac ON at.account_class_id=ac.account_class_id)
            ON ja.account_id=at.account_id)
            ON ji.journal_id=ja.journal_id

            WHERE ji.is_active=TRUE AND ji.is_deleted=FALSE
            AND ac.account_type_id=$type_id
            ".($depid!=null?" AND ji.department_id=$depid":"")."
            ".($start!=null&&$end!=null?" AND ji.date_txn BETWEEN '$start' AND '$end'":"")."

            GROUP BY at.grand_parent_id)as main LEFT JOIN account_titles as att ON main.grand_parent_id=att.account_id";
            return $this->db->query($sql)->result();

    }



    function get_grand_parent_account_subsidiary($start,$end){
        $sql="SELECT ji.date_txn,ji.txn_no,

                IF(ji.supplier_id>0,s.supplier_name,c.customer_name) as particular,
                ja.memo,ji.remarks,ja.dr_amount,ja.cr_amount,ja.account_id

                FROM (journal_info as ji
                LEFT JOIN customers as c ON c.customer_id=ji.customer_id
                LEFT JOIN suppliers as s ON s.supplier_id=ji.supplier_id
                )
                INNER JOIN (journal_accounts as ja
                INNER JOIN account_titles as at ON at.account_id=ja.account_id
                ) ON ja.journal_id=ji.journal_id

                WHERE ji.date_txn BETWEEN '$start' AND '$end'

                ORDER BY ji.date_txn";

        return $this->db->query($sql)->result();

    }

    function get_petty_cash_list($asOfDate=null,$department_id=null) {
        $sql="SELECT
            ji.txn_no,
            ji.supplier_id,
            ji.remarks,
            ji.amount,
            ji.ref_no,
            ji.journal_id,
            ji.department_id,
            d.*,
            DATE_FORMAT(ji.date_txn,'%m/%d/%Y') AS date_txn,
            s.*,
            ja.account_id
            FROM journal_info AS ji
            LEFT JOIN suppliers AS s ON s.supplier_id=ji.`supplier_id`
            INNER JOIN journal_accounts AS ja ON ja.`journal_id`=ji.`journal_id`
            LEFT JOIN account_titles AS atitle ON atitle.`account_id`=ja.`account_id`
            LEFT JOIN `account_classes` AS ac ON ac.`account_class_id`=atitle.`account_class_id`
            LEFT JOIN departments AS d ON d.department_id=ji.department_id
            WHERE
            ji.`is_active`=TRUE AND
            ji.`is_deleted`=FALSE AND
            ji.book_type='PCJ' AND
            ac.`account_type_id`=5 AND
            ji.is_replenished=FALSE AND
            ji.`date_txn` <= '$asOfDate'".
            ($department_id==1 ? "" : " AND ji.department_id = $department_id");
       return $this->db->query($sql)->result();
    }

    function get_general_ledger_report($startDate,$endDate) {
            $sql="SELECT 
                    DATE_FORMAT(ji.date_txn,'%m/%d/%Y') AS date_txn,

                    IF(ji.supplier_id = 0, 'Customer: ', 'Supplier: ') as title,
                    IF(ji.supplier_id = 0, cu.customer_name, su.supplier_name) as name,

                    CONCAT('<b>Date: </b>',DATE_FORMAT(ji.date_txn,'%m/%d/%Y'),' | <b> Book: </b> ',ji.book_type) as group_by,
                    CONCAT(ji.ref_type,'-',ji.ref_no) as reference,
                    cu.customer_name,
                    su.supplier_name,
                    ji.journal_id,
                    ji.txn_no,
                    ji.book_type,
                    ji.remarks,
                    ji.txn_no,
                    ji.ref_type,
                    ji.ref_no
                   
                    FROM journal_info as ji

                    LEFT JOIN customers as cu ON cu.customer_id = ji.customer_id
                    LEFT JOIN suppliers as su ON su.supplier_id = ji.supplier_id

                    WHERE ji.is_active=TRUE AND ji.is_deleted=FALSE
                    AND 
                        ji.date_txn BETWEEN '$startDate' AND '$endDate'
                    GROUP BY ji.txn_no
                    ORDER BY ji.date_txn DESC";
            return $this->db->query($sql)->result();
        }

    function get_general_ledger_items($startDate,$endDate){
             $sql="SELECT 
                    DATE_FORMAT(ji.date_txn,'%m/%d/%Y') AS date_txn,
                    ji.journal_id,
                    ji.txn_no,
                    ji.ref_type,
                    ji.ref_no,
                    ji.book_type,
                    at.account_no, 
                    at.account_title,
                    ja.dr_amount as debit,
                    ja.cr_amount as credit,

                    CONCAT(ji.ref_type,'-',ji.ref_no) as reference,
                    CONCAT('') as a,

                    IF(ji.supplier_id = 0, 'Customer: ', 'Supplier: ') as title,
                    IF(ji.supplier_id = 0, cu.customer_name, su.supplier_name) as name,

                    CONCAT('<b>Date: </b>',DATE_FORMAT(ji.date_txn,'%m/%d/%Y'),' | <b> Book: </b> ',ji.book_type) as group_by

                    FROM journal_accounts as ja

                    LEFT JOIN journal_info as ji ON ji.`journal_id` = ja.`journal_id`
                    LEFT JOIN account_titles AS at ON at.`account_id`=ja.`account_id`
                    LEFT JOIN customers as cu ON cu.customer_id = ji.customer_id
                    LEFT JOIN suppliers as su ON su.supplier_id = ji.supplier_id

                    WHERE ji.is_active=TRUE AND ji.is_deleted=FALSE
                    AND 
                        ji.date_txn BETWEEN '$startDate' AND '$endDate'";
            return $this->db->query($sql)->result();
    }

    function get_remaining_amount($asOfDate=null, $department_id=null) {
        $sql="SELECT
            (CASE WHEN x.`account_type_id` = 1 OR x.account_type_id=5 THEN
            IFNULL(((x.dr_amount) - (x.cr_amount)),0)
            ELSE
            IFNULL(((x.cr_amount) - (x.dr_amount)),0)
            END) as Balance
            FROM
            (SELECT
            petty_cash_account_id,
            ja.journal_id,
            ac.account_type_id,
            SUM(ja.dr_amount) AS dr_amount,
            SUM(ja.cr_amount) AS cr_amount,
            ji.date_txn,
            ji.department_id
            FROM `account_integration` AS ai
            LEFT JOIN journal_accounts AS ja ON ja.account_id=ai.petty_cash_account_id
            LEFT JOIN account_titles AS atitles ON atitles.account_id=ai.petty_cash_account_id
            LEFT JOIN account_classes AS ac ON ac.`account_class_id`=atitles.`account_class_id`
            LEFT JOIN journal_info AS ji ON ji.journal_id=ja.`journal_id` AND ji.is_active=TRUE AND ji.is_deleted=FALSE
            WHERE is_replenished=FALSE AND date_txn <= '$asOfDate' ".($department_id == 1 ? ") as x" : " AND ji.department_id=".$department_id.") AS x ");


        return $this->db->query($sql)->result();

    }



}

?>
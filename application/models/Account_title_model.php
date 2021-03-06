<?php

class Account_title_model extends CORE_Model{

    protected  $table="account_titles"; //table name
    protected  $pk_id="account_id"; //primary key id


    function __construct()
    {
        // Call the Model constructor
        parent::__construct();
    }


    function create_default_account_title(){
        //return;
        $sql="INSERT IGNORE INTO account_titles
                  (account_id,account_no,account_title,account_class_id,parent_account_id,grand_parent_id)
              VALUES
                  (1,'101','Cash',1,0,1),
                  (2,'120','Account Receivable',1,0,2),
                  (3,'140','Inventory',1,0,3),
                  (10,'150','Input Tax',1,0,10),

                  (4,'210','Accounts Payable',3,0,4),
                  (11,'220','Output Tax',3,0,4),

                  (5,'300','Capital',5,0,5),

                  (6,'400','Sales Income',7,0,6),
                  (7,'410','Service Income',7,0,7),


                  (8,'500','Salaries Expense',6,0,8),
                  (9,'510','Supplies Expense',6,0,9),
                  (12,'510','Miscellaneous Expense',6,0,12)
        ";
        $this->db->query($sql);
    }

    function validate_account_no($account_no,$account_id=null){
        $sql="SELECT * FROM account_titles 
            WHERE is_deleted = FALSE AND is_active = TRUE AND
            account_no = '".$account_no."'
            ".($account_id==null?"":" AND account_id!=".$account_id)."";
        return $this->db->query($sql)->result();
    }   

    function validate_account_title($account_title,$account_id=null){
        $sql="SELECT * FROM account_titles 
            WHERE is_deleted = FALSE AND is_active = TRUE AND
            account_title = '".$account_title."'
            ".($account_id==null?"":" AND account_id!=".$account_id)."";
        return $this->db->query($sql)->result();
    }   

    function get_account_types(){
        $sql="SELECT * FROM account_types  
        order by account_type_id ASC";
        return $this->db->query($sql)->result();
    }   
    function get_account_classes(){

        $sql="SELECT * FROM account_classes  
        WHERE is_active = TRUE and is_deleted = FALSE 
        order by account_type_id ASC";
        return $this->db->query($sql)->result();
    }
    function get_account_titles(){

        $sql="SELECT at.* FROM account_titles at
        WHERE at.is_active = TRUE AND at.is_deleted = FALSE AND at.parent_account_id = 0
        ORDER BY at.account_no
        ";
        return $this->db->query($sql)->result();
    }

    function get_account_titles_child(){

        $sql="SELECT at.* FROM account_titles at
        WHERE at.is_active = TRUE AND at.is_deleted = FALSE AND at.parent_account_id != 0
        ORDER BY at.account_no
        ";
        return $this->db->query($sql)->result();
    }
    
    function get_account_titles_balance($start=null,$end=null){
        $sql="SELECT

                at.account_no,at.account_title,
                IFNULL(SUM(ja.dr_amount),0) as dr_amount,
                IFNULL(SUM(ja.cr_amount),0) as cr_amount,
                ac.account_class_id,ac.account_type_id,

                IF(
                    ac.account_type_id=1 OR ac.account_type_id=5,
                    IFNULL(SUM(ja.dr_amount),0)-IFNULL(SUM(ja.cr_amount),0),
                    IFNULL(SUM(ja.cr_amount),0)-IFNULL(SUM(ja.dr_amount),0)
                ) as balance


                FROM (account_titles as at LEFT JOIN `account_classes` as ac ON at.`account_class_id`=ac.account_class_id)

                LEFT JOIN

                (

                    SELECT ja.journal_id,ja.account_id,
                    SUM(dr_amount)as dr_amount,
                    SUM(cr_amount)as cr_amount
                    FROM journal_accounts as ja
                    INNER JOIN journal_info as ji ON ja.journal_id=ji.journal_id

                    LEFT JOIN

                            (

                                SELECT jax.journal_id FROM journal_accounts as jax WHERE jax.account_id IN(
                                    SELECT atx.account_id FROM account_titles as atx
                                    INNER JOIN account_classes as ac ON ac.`account_class_id`=atx.account_class_id
                                    WHERE atx.is_deleted=TRUE OR ac.is_deleted=TRUE
                                    )

                                GROUP BY jax.journal_id

                            )as lQ ON lQ.journal_id=ja.journal_id


                    WHERE ji.is_active=TRUE AND ji.is_deleted=FALSE AND ISNULL(lQ.journal_id)


                    ".($start!=null&&$end!=null?" AND ji.date_txn BETWEEN '$start' AND '$end'":"")."



                    GROUP BY ja.account_id

                )as ja

                ON at.account_id=ja.account_id

                WHERE at.is_deleted=FALSE AND at.is_active=TRUE AND ac.is_active=TRUE AND ac.is_deleted=FALSE

                GROUP BY at.account_id";

            return $this->db->query($sql)->result();
    }




}




?>
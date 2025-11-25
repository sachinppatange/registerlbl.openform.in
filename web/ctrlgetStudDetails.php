<?php
include_once '../config/config.php';

function getAllStudent()
{
    $leftAr = array();
    $query = "select * from student order by stud_id DESC";
    $result = mysqli_query($GLOBALS['conn'], $query);
    $count = mysqli_num_rows($result);
    if ($count > 0) {
        while ($arr = mysqli_fetch_array($result)) {
            array_push($leftAr, $arr);
        }
    }
    return $leftAr;
}
function getStudentById($stud_id)
{
    $arr = array();
    $sql = "select * from student where `createdby`='$stud_id'";
    $res = mysqli_query($GLOBALS['conn'], $sql);
    $count = mysqli_num_rows($res);
    if ($count == 1) {
        $arr = mysqli_fetch_array($res);
        return $arr;
    }
}
function getStudentByStudId($stud_id)
{
    $arr = array();
    $sql = "select * from student where `stud_id`='$stud_id'";

    $res = mysqli_query($GLOBALS['conn'], $sql);
    // print_r($res);
    // exit;
    $count = mysqli_num_rows($res);
    if ($count == 1) {
        $arr = mysqli_fetch_array($res);
        return $arr;
    }
}

function getStudentMobileById($id)
{
    $arr = array();
    $sql = "select * from student where `createdby`='$id'";
    $res = mysqli_query($GLOBALS['conn'], $sql);
    $count = mysqli_num_rows($res);
    if ($count == 1) {
        $arr = mysqli_fetch_array($res);
        return $arr;
    }
}
function getStudentPaymentStatusById($stud_id)
{
    $arr = array();
    $sql = "select * from student where `createdby`='$stud_id'";
    $res = mysqli_query($GLOBALS['conn'], $sql);
    $count = mysqli_num_rows($res);
    if ($count == 1) {
        $arr = mysqli_fetch_array($res);
        return $arr;
    }
}
function getAllRegStudent()
{
    $leftAr = array();
    $query = "select * from student order by stud_id  DESC";
    $result = mysqli_query($GLOBALS['conn'], $query);
    $count = mysqli_num_rows($result);
    if ($count > 0) {
        while ($arr = mysqli_fetch_array($result)) {
            array_push($leftAr, $arr);
        }
    }
    return $leftAr;
}
function getStudentRegById($stud_id)
{
    $arr = array();
    $sql = "select * from student where `stud_id`='$stud_id'";
    $res = mysqli_query($GLOBALS['conn'], $sql);
    $count = mysqli_num_rows($res);
    if ($count == 1) {
        $arr = mysqli_fetch_array($res);
        return $arr;
    }
}


function getStudentRegByMaxId($stud_id)
{
    $arr = array();
    $sql = "SELECT * FROM student WHERE stud_id  = (SELECT MAX(stud_id ) FROM student);
";
    $res = mysqli_query($GLOBALS['conn'], $sql);
    $count = mysqli_num_rows($res);
    if ($count == 1) {
        $arr = mysqli_fetch_array($res);
        return $arr;
    }
}





function gettotalStudents()
{
    $sql = "SELECT COUNT(*) AS total_records FROM student";
    $res = mysqli_query($GLOBALS['conn'], $sql);
    $row = mysqli_fetch_assoc($res);
    return $row['total_records'];
}
function gettotalMembers()
{
    $sql = "SELECT COUNT(*) AS total_records FROM student";
    $res = mysqli_query($GLOBALS['conn'], $sql);
    $row = mysqli_fetch_assoc($res);
    return $row['total_records'];
}
function getTotalPaidStudent()
{
    $sql = "SELECT COUNT(*) AS total_records FROM student WHERE status = 'success'";
    $res = mysqli_query($GLOBALS['conn'], $sql);

    if ($res) {
        $row = mysqli_fetch_assoc($res);
        return $row['total_records'];
    } else {
        return 0;
    }
}
function getTotalunPaidStudent()
{
    $sql = "SELECT COUNT(*) AS total_records 
    FROM student 
    WHERE status IS NULL OR status != 'success' OR status = ''";
    // $sql = "SELECT COUNT(*) AS total_records FROM student WHERE status != 'Success'";
    $res = mysqli_query($GLOBALS['conn'], $sql);

    if ($res) {
        $row = mysqli_fetch_assoc($res);
        return $row['total_records'];
    } else {
        return 0;
    }
}







function getAllPaidStudent()
{
    $leftAr = array();
    $query = "select * from student where status ='success' order by stud_id DESC";
    $result = mysqli_query($GLOBALS['conn'], $query);
    $count = mysqli_num_rows($result);
    if ($count > 0) {
        while ($arr = mysqli_fetch_array($result)) {
            array_push($leftAr, $arr);
        }
    }
    return $leftAr;
}

function getAllUnpaidStudent()
{
    $leftAr = array();
    $query = "SELECT * 
    FROM student 
    WHERE status IS NULL OR status != 'success' OR status = '' 
    ORDER BY stud_id DESC";

    $result = mysqli_query($GLOBALS['conn'], $query);
    $count = mysqli_num_rows($result);
    if ($count > 0) {
        while ($arr = mysqli_fetch_array($result)) {
            array_push($leftAr, $arr);
        }
    }
    return $leftAr;
}







?>
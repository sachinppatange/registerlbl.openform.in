<?php
session_start();
include_once '../config/config.php';

if (isset($_POST['submit'])) {

    // सुरक्षित डेटा ऍक्सेस
    $studid       = $_POST['studid'] ?? '';
    $surname      = $_POST['surname'] ?? '';
    $firstname    = $_POST['firstname'] ?? '';
    $fathers_name = $_POST['fathername'] ?? '';
    $mothername   = $_POST['mothername'] ?? '';
    $email        = $_POST['email'] ?? '';
    $whatsapp     = $_POST['whatsapp'] ?? '';
    $alternateno  = $_POST['alternateno'] ?? '';
    $password     = $_POST['password'] ?? '';
    $aadhar       = $_POST['aadhar'] ?? '';
    $course       = $_POST['course'] ?? '';
    $dob          = $_POST['dob'] ?? '';
    $adcategory   = $_POST['adcategory'] ?? '';
    $category     = $_POST['category'] ?? '';
    $address      = $_POST['address'] ?? '';
    $schoolname   = $_POST['schoolname'] ?? '';
    $previousstd  = $_POST['previousstd'] ?? '';
    $grade        = $_POST['grade'] ?? '';
    $board        = $_POST['board'] ?? '';
    $language     = $_POST['language'] ?? '';
    $payment      = $_POST['payment'] ?? '';

    // Optional center fields
    $center1 = $_POST['centre1'] ?? '';
    $center2 = $_POST['firstcenter'] ?? '';
    $center3 = $_POST['secondcenter'] ?? '';
    $center4 = $_POST['thirdcenter'] ?? '';
    $center5 = $_POST['centre5'] ?? '';

    $centerarr = [
        'centre1' => $center1,
        'centre2' => $center2,
        'centre3' => $center3,
        'centre4' => $center4,
        'centre5' => $center5
    ];
    $jsonarr = json_encode($centerarr);

    // जर session मध्ये createdby नसेल तर default 0 द्या
    $createdby = $_SESSION['id'] ?? 0;
    $createdon = date('Y-m-d H:i:s');

    // मोबाइल नंबर duplicate तपासा
    $query = "SELECT * FROM student WHERE whatsappno = '$whatsapp'";
    $result = mysqli_query($GLOBALS['conn'], $query);
    $count = mysqli_num_rows($result);
    if ($count > 0) {
        $_SESSION['error1'] = "Mobile Number already exists.";
        header('location:../web/index.php');
        exit;
    }

    // Upload directory check
    $targetDir = "../uploads/";
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    // --- PHOTO ---
    $studphoto = $_FILES['studphoto']['name'] ?? '';
    $targetFilePathPhoto = '';
    if (!empty($studphoto)) {
        $extstudphoto = pathinfo($studphoto, PATHINFO_EXTENSION);
        $photoname = "photo_" . $whatsapp . "." . $extstudphoto;
        $targetFilePathPhoto = $targetDir . $photoname;
        move_uploaded_file($_FILES['studphoto']['tmp_name'], $targetFilePathPhoto);
    }

    // --- AADHAR ---
    $studaadhar = $_FILES['studaadhar']['name'] ?? '';
    $targetFilePathAadhar = '';
    if (!empty($studaadhar)) {
        $extstudaadhar = pathinfo($studaadhar, PATHINFO_EXTENSION);
        $aadharname = "aadhar_" . $whatsapp . "." . $extstudaadhar;
        $targetFilePathAadhar = $targetDir . $aadharname;
        move_uploaded_file($_FILES['studaadhar']['tmp_name'], $targetFilePathAadhar);
    }

    // --- SIGNATURE ---
    $studsign = $_FILES['studsign']['name'] ?? '';
    $targetFilePathSign = '';
    if (!empty($studsign)) {
        $extstudsign = pathinfo($studsign, PATHINFO_EXTENSION);
        $signname = "sign_" . $whatsapp . "." . $extstudsign;
        $targetFilePathSign = $targetDir . $signname;
        move_uploaded_file($_FILES['studsign']['tmp_name'], $targetFilePathSign);
    }

    // ✅ INSERT NEW STUDENT
    if (empty($studid)) {
        $query = "INSERT INTO `student`
        (`stud_id`, `surname`, `firstname`, `fathername`, `mothername`, `email`, `whatsappno`, `alternateno`, `aadhar`, `course`, `dob`, `adcategory`, `category`, `address`, `schoolname`, `previousstd`, `grade`, `board`, `language`, `studphoto`, `studaadhar`, `studsign`, `centre`, `amount`, `createdby`, `createdon`)
        VALUES
        (NULL, '$surname', '$firstname', '$fathers_name', '$mothername', '$email', '$whatsapp', '$alternateno', '$aadhar', '$course', '$dob', '$adcategory', '$category', '$address', '$schoolname', '$previousstd', '$grade', '$board', '$language', '$targetFilePathPhoto', '$targetFilePathAadhar', '$targetFilePathSign', '$center1,$center2,$center3,$center4,$center5', '$payment', '$createdby', '$createdon')";

        $result = mysqli_query($GLOBALS['conn'], $query);

        if ($result) {
            $res = mysqli_query($GLOBALS['conn'], "SELECT MAX(stud_id) AS sid FROM student");
            $arr = mysqli_fetch_assoc($res);
            $studmaxid = $arr['sid'] ?? 0;

            $_SESSION['id'] = $studmaxid;
            $_SESSION['username'] = $email;
            $_SESSION['type'] = 'student';
            $_SESSION['message'] = "Redirecting for Payment";

            header("Location: ../web/index.php?studmaxid=$studmaxid");
            exit;
        } else {
            echo "Database insert error: " . mysqli_error($GLOBALS['conn']);
        }

    } else {
        // UPDATE code
        $query = "UPDATE student SET
            surname='$surname',
            firstname='$firstname',
            fathername='$fathers_name',
            mothername='$mothername',
            email='$email',
            whatsappno='$whatsapp',
            alternateno='$alternateno',
            aadhar='$aadhar',
            course='$course',
            dob='$dob',
            adcategory='$adcategory',
            category='$category',
            address='$address',
            schoolname='$schoolname',
            previousstd='$previousstd',
            grade='$grade',
            board='$board',
            language='$language',
            amount='$payment',
            createdon='$createdon'
            WHERE stud_id='$studid'";
        mysqli_query($GLOBALS['conn'], $query);

        header('Location: ../admin/member.php');
        exit;
    }
}
?>

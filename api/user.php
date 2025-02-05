<?php
include "headers.php";

class User
{
    function login($json)
    {
        // {"username":"example_username","password":"example_password"}
        include "connection.php";

        $json = json_decode($json, true);

        // Query for scholars
        $sqlScholars = "SELECT a.*, b.userLevel_name 
        FROM tbl_scholars a
        LEFT JOIN tbl_user_level b 
            ON b.userLevel_privilege = a.stud_user_level
        WHERE (a.stud_id = :username OR a.stud_email = :username) 
        AND BINARY a.stud_password = :password";

        $stmtScholars = $conn->prepare($sqlScholars);
        $stmtScholars->bindParam(":username", $json["username"]);
        $stmtScholars->bindParam(":password", $json["password"]);
        $stmtScholars->execute();

        if ($stmtScholars->rowCount() > 0) {
            $user = $stmtScholars->fetch(PDO::FETCH_ASSOC);
            return json_encode([
                "stud_id" => $user["stud_id"],
                "stud_academic_session_id" => $user["stud_academic_session_id"],
                "stud_name" => $user["stud_name"],
                "stud_scholarship_id" => $user["stud_scholarship_id"],
                "stud_department_id" => $user["stud_department_id"],
                "stud_course_id" => $user["stud_course_id"],
                "stud_year_id" => $user["stud_year_id"],
                "stud_status_id" => $user["stud_status_id"],
                "stud_percent_id" => $user["stud_percent_id"],
                "stud_amount" => $user["stud_amount"],
                "stud_applied_on_misc" => $user["stud_applied_on_misc"],
                "stud_date" => $user["stud_date"],
                "stud_modified_by" => $user["stud_modified_by"],
                "stud_modified_date" => $user["stud_modified_date"],
                "stud_password" => $user["stud_password"],
                "stud_image_filename" => $user["stud_image_filename"],
                "stud_contactNumber" => $user["stud_contactNumber"],
                "stud_email" => $user["stud_email"],
                "stud_user_level" => $user["userLevel_name"]
            ]);
        }

        // Query for supervisors
        $sqlSupervisors = "SELECT * 
        FROM tbl_supervisors_master 
        WHERE (supM_id = :username OR supM_email = :username) 
        AND BINARY supM_password = :password";

        $stmtSupervisors = $conn->prepare($sqlSupervisors);
        $stmtSupervisors->bindParam(":username", $json["username"]);
        $stmtSupervisors->bindParam(":password", $json["password"]);
        $stmtSupervisors->execute();

        if ($stmtSupervisors->rowCount() > 0) {
            $user = $stmtSupervisors->fetch(PDO::FETCH_ASSOC);
            return json_encode([
                "supM_id" => $user["supM_id"],
                "supM_name" => $user["supM_name"],
                "supM_password" => $user["supM_password"],
                "supM_email" => $user["supM_email"],
                "supM_image_filename" => $user["supM_image_filename"]
            ]);
        }

        return 0;
    }



    function adminLogin($json)
    {
        // {"username":"Manu.jabulan.coc@phinmaed.com","password":"Ja02-2021-03668"}
        include "connection.php";
        $json = json_decode($json, true);
        $sql = "SELECT * FROM tbl_admin WHERE BINARY adm_email = :username AND BINARY adm_password = :password";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam("username", $json["username"]);
        $stmt->bindParam("password", $json["password"]);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        return 0;
    }

    function updateImage($json)
    {
        try {
            include "connection.php";
            $json = json_decode($json, true);
            $returnValueImage = $this->uploadImage();
            switch ($returnValueImage) {
                case 2:
                    // You cannot Upload files of this type!
                    return 2;
                case 3:
                    // There was an error uploading your file!
                    return 3;
                case 4:
                    // Your file is too big (25mb maximum)
                    return 4;
                default:
                    break;
            }

            $sql = "UPDATE tbl_admin SET adm_image = :image WHERE adm_id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam("image", $returnValueImage);
            $stmt->bindParam("id", $json["userId"]);
            $stmt->execute();
            return $stmt->rowCount() > 0 ? $returnValueImage : 0;
        } catch (\Throwable $th) {
            return $th;
        }
    }


    function uploadImage()
    {
        if (isset($_FILES["file"])) {
            $file = $_FILES['file'];
            // print_r($file);
            $fileName = $_FILES['file']['name'];
            $fileTmpName = $_FILES['file']['tmp_name'];
            $fileSize = $_FILES['file']['size'];
            $fileError = $_FILES['file']['error'];
            // $fileType = $_FILES['file']['type'];

            $fileExt = explode(".", $fileName);
            $fileActualExt = strtolower(end($fileExt));

            $allowed = ["jpg", "jpeg", "png", "gif", "webp"];

            if (in_array($fileActualExt, $allowed)) {
                if ($fileError === 0) {
                    if ($fileSize < 25000000) {
                        $fileNameNew = uniqid("", true) . "." . $fileActualExt;
                        $fileDestination =  'images/' . $fileNameNew;
                        move_uploaded_file($fileTmpName, $fileDestination);
                        return $fileNameNew;
                    } else {
                        return 4;
                    }
                } else {
                    return 3;
                }
            } else {
                return 2;
            }
        } else {
            return "";
        }

        // $returnValueImage = uploadImage();

        // switch ($returnValueImage) {
        //     case 2:
        //         // You cannot Upload files of this type!
        //         return 2;
        //     case 3:
        //         // There was an error uploading your file!
        //         return 3;
        //     case 4:
        //         // Your file is too big (25mb maximum)
        //         return 4;
        //     default:
        //         break;
        // }
    }
}
$json = isset($_POST["json"]) ? $_POST["json"] : "0";
$operation = isset($_POST["operation"]) ? $_POST["operation"] : "0";
$user = new User();
switch ($operation) {
    case "login":
        echo $user->login($json);
        break;
    case "adminLogin":
        echo json_encode($user->adminLogin($json));
        break;
    case "updateImage":
        echo $user->updateImage($json);
        break;
        // case "getAdmin":
        //     echo $user->getAdmin();
        //     break;
        // case "getscholarship_type":
        //     echo $user->getscholarship_type();
        //     break;
        // case "getcourse":
        //     echo $user->getcourse();
        //     break;
        // case "getscholarship_type_list":
        //     echo $user->getscholarship_type_list();
        //     break;
        // case "getSubType":
        //     echo $user->getSubType();
        //     break;
        // case "getschoolyear":
        //     echo $user->getschoolyear();
    default:
        echo "WALAY " . $operation . " NGA OPERATION SA UBOS HAHHAHA BOBO NOYNAY";
        break;
}

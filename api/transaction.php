<?php
include "connection.php";
include "headers.php";

class Transaction
{
    function getStudentsDetailsAndStudentDutyAssign($json)
    {
        include "connection.php";
        $json = json_decode($json, true);

        $sql = "
        SELECT p.stud_active_id, a.stud_name AS StudentFullname, e.sub_code, e.sub_descriptive_title, e.sub_section, e.sub_time, e.sub_room, f.day_name AS F2F_Day, n.day_name AS RC_Day, m.day_name AS F2F_Day_Office, g.supM_name AS AdvisorFullname, i.dutyH_name AS TotalDutyHours, dept_name, d.offT_time, j.build_name, h.learning_name, i.dutyH_name - (SELECT SUM(TIMESTAMPDIFF(HOUR, k2.dtr_current_time_in, k2.dtr_current_time_out)) FROM tbl_dtr AS k2 WHERE k2.dtr_assign_id = b.assign_id AND k2.dtr_current_time_in <= k.dtr_current_time_in) AS RemainingHours,(SELECT SUM(TIMESTAMPDIFF(HOUR, k2.dtr_current_time_in, k2.dtr_current_time_out)) FROM tbl_dtr AS k2 WHERE k2.dtr_assign_id = b.assign_id) AS TotalRenderedHours, b.assign_render_status 
FROM tbl_scholars AS a
INNER JOIN tbl_activescholars AS p ON p.stud_active_id = a.stud_id
LEFT JOIN tbl_assign_scholars AS b ON b.assign_stud_id = p.stud_active_id
LEFT JOIN tbl_office_master AS c ON c.off_id = b.assign_office_id
LEFT JOIN tbl_office_type AS d ON d.offT_id = c.off_type_id
LEFT JOIN tbl_subjects AS e ON e.sub_id = c.off_subject_id
LEFT JOIN tbl_day AS f ON f.day_id = e.sub_day_f2f_id 
LEFT JOIN tbl_supervisors_master AS g ON g.supM_id = e.sub_supM_id
LEFT JOIN tbl_learning_modalities AS h ON h.learning_id = e.sub_learning_modalities_id
LEFT JOIN tbl_duty_hours AS i ON i.dutyH_id = b.assign_duty_hours_id
LEFT JOIN tbl_department AS o ON o.dept_id = d.offT_dept_id
LEFT JOIN tbl_building AS j ON j.build_id = o.dept_build_id
LEFT JOIN tbl_dtr AS k ON k.dtr_assign_id = b.assign_id
LEFT JOIN tbl_day AS m ON m.day_id = d.offT_day_id
LEFT JOIN tbl_day AS n ON n.day_id = e.sub_day_rc_id 

WHERE stud_active_id = :stud_active_id
ORDER BY k.dtr_current_time_in DESC 
LIMIT 1
    
    ";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':stud_active_id', $json['stud_active_id']);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result['RemainingHours'] <= 0) {
            $updateSql = "
            UPDATE tbl_assign_scholars
            SET assign_render_status = 1
            WHERE assign_stud_id = :stud_active_id
        ";

            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bindParam(':stud_id', $json['stud_id']);
            $updateStmt->execute();
        }

        return json_encode($result);
    }



    function getStudentDtr($json)
    {
        include "connection.php";
        $json = json_decode($json, true);
        $sql = "SELECT 
                a.stud_active_id, 
                b.stud_name AS StudentFullname, 
                d.dtr_date, 
                e.session_name, 
                TIME(d.dtr_current_time_in) AS dtr_time_in, 
                TIME(d.dtr_current_time_out) AS dtr_time_out,
                TIMEDIFF(TIME(d.dtr_current_time_out), TIME(d.dtr_current_time_in)) AS TotalRendered
            FROM 
                tbl_activescholars AS a
            INNER JOIN tbl_scholars AS b ON b.stud_id = a.stud_active_id
            LEFT JOIN 
                tbl_assign_scholars AS c ON c.assign_stud_id = a.stud_active_id
            LEFT JOIN 
                tbl_dtr AS d ON d.dtr_assign_id = c.assign_id
            LEFT JOIN 
                tbl_academic_session AS e ON e.session_id = c.assign_session_id
            WHERE 
                a.stud_active_id = :stud_active_id
            ORDER BY 
                d.dtr_date";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':stud_active_id', $json['stud_active_id']);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalRendered = 0;

        foreach ($result as $row) {
            // Validate TotalRendered
            if (!empty($row['TotalRendered']) && preg_match('/^\d{2}:\d{2}:\d{2}$/', $row['TotalRendered'])) {
                list($hours, $minutes, $seconds) = explode(":", $row['TotalRendered']);
                $totalRendered += ($hours * 3600) + ($minutes * 60) + $seconds;
            }
        }

        // Convert total rendered time from seconds back to HH:MM:SS format
        $totalHours = floor($totalRendered / 3600);
        $totalMinutes = floor(($totalRendered % 3600) / 60);
        $totalSeconds = $totalRendered % 60;
        $totalRenderedFormatted = sprintf("%02d:%02d:%02d", $totalHours, $totalMinutes, $totalSeconds);

        // Append total rendered time to each student's data
        foreach ($result as $key => $row) {
            $result[$key]['TotalRenderedForStudent'] = $totalRenderedFormatted;
        }

        return json_encode($result);
    }

    function studentsAttendance($json)
    {
        include "connection.php";

        $json = json_decode($json, true);
        $scannedID = $json['stud_active_id'];
        $currentDate = date('Y-m-d'); // Today's date

        // Step 1: Fetch the latest DTR data for the scanned student
        $sql = "
        SELECT a.stud_active_id, b.assign_id AS assign_id, c.dtr_id, c.dtr_date, c.dtr_current_time_in, c.dtr_current_time_out
        FROM tbl_activescholars AS a
        INNER JOIN tbl_assign_scholars AS b ON b.assign_stud_id = a.stud_active_id
        LEFT JOIN tbl_dtr AS c ON c.dtr_assign_id = b.assign_id AND c.dtr_date = :currentDate
        WHERE a.stud_active_id = :scannedID
        ORDER BY c.dtr_date DESC LIMIT 1
    ";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':scannedID', $scannedID, PDO::PARAM_STR);
        $stmt->bindValue(':currentDate', $currentDate, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Step 2: If no DTR record exists for today, insert new time_in
        if (!$result || empty($result['dtr_id'])) {
            $assignID = $result['assign_id']; // From tbl_assign_scholars
            $sqlInsertTimeIn = "
            INSERT INTO tbl_dtr (dtr_assign_id, dtr_date, dtr_current_time_in) 
            VALUES (:assignID, CURDATE(), NOW())
        ";
            $stmtInsert = $conn->prepare($sqlInsertTimeIn);
            $stmtInsert->bindValue(':assignID', $assignID, PDO::PARAM_INT);

            return $stmtInsert->execute() ? 1 : 0;
        }

        // Step 3: If time_in exists but time_out is empty, update time_out
        if (!empty($result['dtr_current_time_in']) && empty($result['dtr_current_time_out'])) {
            $sqlUpdateTimeOut = "
            UPDATE tbl_dtr 
            SET dtr_current_time_out = NOW()
            WHERE dtr_id = :dtr_id
        ";
            $stmtUpdate = $conn->prepare($sqlUpdateTimeOut);
            $stmtUpdate->bindValue(':dtr_id', $result['dtr_id'], PDO::PARAM_INT);

            return $stmtUpdate->execute() ? 1 : 0;
        }

        // Step 4: Default return if no other condition matches
        return 0;
    }

    function getAssignedScholars($json)
    {
        include "connection.php";
        $json = json_decode($json, true);

        $supM_id = $json['sub_supM_id'];

        $filteredResult = [];

        // Query for sub_supM_id
        $sqlSubSup = "SELECT b.stud_active_id, a.stud_name AS Fullname, a.stud_contactNumber, a.stud_email,
                              sub_code, sub_descriptive_title, g.sub_section, sub_time, sub_room,
                              f.dutyH_name
                       FROM tbl_scholars AS a
                       INNER JOIN tbl_activescholars AS b ON b.stud_active_id = a.stud_id
                       LEFT JOIN tbl_assign_scholars AS c ON c.assign_stud_id = b.stud_active_id
                       LEFT JOIN tbl_office_master AS d ON d.off_id = c.assign_office_id
                       LEFT JOIN tbl_office_type AS e ON e.offT_id = d.off_type_id
                       LEFT JOIN tbl_duty_hours AS f ON f.dutyH_id = c.assign_duty_hours_id
                       LEFT JOIN tbl_subjects AS g ON g.sub_id = d.off_subject_id
                       LEFT JOIN tbl_supervisors_master AS h ON h.supM_id = g.sub_supM_id
                       WHERE g.sub_supM_id = :supM_id";

        $stmtSubSup = $conn->prepare($sqlSubSup);
        $stmtSubSup->bindParam(":supM_id", $supM_id);
        $stmtSubSup->execute();
        $resultSubSup = $stmtSubSup->fetchAll(PDO::FETCH_ASSOC);

        foreach ($resultSubSup as $row) {
            $filteredResult[] = [
                'stud_active_id' => $row['stud_active_id'],
                'Fullname' => $row['Fullname'],
                'stud_contactNumber' => $row['stud_contactNumber'],
                'stud_email' => $row['stud_email'],
                'sub_code' => $row['sub_code'],
                'sub_descriptive_title' => $row['sub_descriptive_title'],
                'sub_section' => $row['sub_section'] ?? null, // Handle if null
                'sub_time' => $row['sub_time'],
                'sub_room' => $row['sub_room'],
                'dutyH_name' => $row['dutyH_name']
            ];
        }

        // Query for offT_supM_id
        $sqlOffTSup = "SELECT b.stud_active_id, a.stud_name AS Fullname, a.stud_contactNumber, a.stud_email,
                              h.dutyH_name, f.build_name, g.day_name, c.assign_render_status,
                              dept_name
                       FROM tbl_scholars AS a
                       INNER JOIN tbl_activescholars AS b ON b.stud_active_id = a.stud_id
                       LEFT JOIN tbl_assign_scholars AS c ON c.assign_stud_id = b.stud_active_id
                       LEFT JOIN tbl_office_master AS d ON d.off_id = c.assign_office_id
                       LEFT JOIN tbl_office_type AS e ON e.offT_id = d.off_type_id
                       LEFT JOIN tbl_day AS g ON g.day_id = e.offT_day_id
                       LEFT JOIN tbl_duty_hours AS h ON h.dutyH_id = c.assign_duty_hours_id
                       LEFT JOIN tbl_department AS j ON j.dept_id = e.offT_dept_id
                       LEFT JOIN tbl_building AS f ON f.build_id = j.dept_build_id
                       WHERE e.offT_supM_id = :supM_id";

        $stmtOffTSup = $conn->prepare($sqlOffTSup);
        $stmtOffTSup->bindParam(":supM_id", $supM_id);
        $stmtOffTSup->execute();
        $resultOffTSup = $stmtOffTSup->fetchAll(PDO::FETCH_ASSOC);

        foreach ($resultOffTSup as $row) {
            $filteredResult[] = [
                'stud_active_id' => $row['stud_active_id'],
                'Fullname' => $row['Fullname'],
                'stud_contactNumber' => $row['stud_contactNumber'],
                'stud_email' => $row['stud_email'],
                'dutyH_name' => $row['dutyH_name'],
                'build_name' => $row['build_name'],
                'day_name' => $row['day_name'],
                'assign_render_status' => $row['assign_render_status'],
                'dept_name' => $row['dept_name']
            ];
        }

        return json_encode($filteredResult);
    }



    function submitStudentFacilitatorEvaluation($json)
    {
        include "connection.php";

        $conn->beginTransaction();
        $json = json_decode($json, true);

        $sql = "INSERT INTO tbl_evaluation_sf (evaluation_sf_assign_stud_id, evaluation_sf_total_perfomance, evaluation_sf_total_general_attributes, evaluation_sf_attendance, evaluation_sf_overall_score, evaluation_sf_supM_id) 
            VALUES (:evaluation_sf_assign_stud_id, :evaluation_sf_total_perfomance, :evaluation_sf_total_general_attributes, :evaluation_sf_attendance, :evaluation_sf_overall_score, :evaluation_sf_supM_id)";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(":evaluation_sf_assign_stud_id", $json['evaluation_sf_assign_stud_id']);
        $stmt->bindParam(":evaluation_sf_total_perfomance", $json['evaluation_sf_total_perfomance']);
        $stmt->bindParam(":evaluation_sf_total_general_attributes", $json['evaluation_sf_total_general_attributes']);
        $stmt->bindParam(":evaluation_sf_attendance", $json['evaluation_sf_attendance']);
        $stmt->bindParam(":evaluation_sf_overall_score", $json['evaluation_sf_overall_score']);
        $stmt->bindParam(":evaluation_sf_supM_id", $json['evaluation_sf_supM_id']);

        if ($stmt->execute()) {
            $conn->commit();
            return 1;
        } else {
            $conn->rollBack();
            return 0;
        }
    }

    //ADMIN SIDE

    function getStudentDutyData()
    {
        include "connection.php";

        $sql = "
            SELECT 
                a.stud_id, 
                a.stud_name, 
                c.dutyH_name, 
                SUM(TIMESTAMPDIFF(HOUR, d.dtr_current_time_in, d.dtr_current_time_out)) AS total_rendered_hours,
                (180 - SUM(TIMESTAMPDIFF(HOUR, d.dtr_current_time_in, d.dtr_current_time_out))) AS remaining_hours
            FROM tbl_scholars AS a
            INNER JOIN tbl_assign_scholars AS b ON b.assign_stud_id = a.stud_id
            INNER JOIN tbl_duty_hours AS c ON c.dutyH_id = b.assign_duty_hours_id
            INNER JOIN tbl_dtr AS d ON d.dtr_assign_id = b.assign_id
            GROUP BY a.stud_id, a.stud_name, c.dutyH_name
        ";

        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return json_encode($result);
    }

    function getActiveAndAssignedScholars()
    {
        include "connection.php";

        // Query to count active and assigned scholars
        $sql = "
        SELECT 
            COUNT(CASE WHEN b.assign_stud_id IS NOT NULL THEN 1 END) AS assigned_count,
            COUNT(*) AS total_count
        FROM tbl_activescholars AS a
        LEFT JOIN tbl_assign_scholars AS b ON b.assign_stud_id = a.stud_active_id";

        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Calculate percentages
        $total = $result['total_count'];
        $assignedPercentage = $total > 0 ? ($result['assigned_count'] / $total) * 100 : 0;
        $activePercentage = $total > 0 ? (($total - $result['assigned_count']) / $total) * 100 : 0;

        // Ensure proper JSON encoding
        echo json_encode([
            'assigned' => round($assignedPercentage, 2),
            'active' => round($activePercentage, 2),
        ]);
    }

    function getActiveAndRenewedScholars()
    {
        include "connection.php";

        // Updated SQL query to count assigned and renewal scholars
        $sql = "
    SELECT 
        COUNT(CASE WHEN b.renewal_assign_stud_active_id IS NOT NULL THEN 1 END) AS renewal_count,
        COUNT(*) AS total_count
    FROM tbl_assign_scholars AS a
    LEFT JOIN tbl_renewal AS b ON b.renewal_assign_stud_active_id = a.assign_stud_id";

        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Calculate percentages
        $total = $result['total_count'];
        $renewalPercentage = $total > 0 ? ($result['renewal_count'] / $total) * 100 : 0;
        $assignedPercentage = $total > 0 ? (($total - $result['renewal_count']) / $total) * 100 : 0;

        // Ensure proper JSON encoding
        echo json_encode([
            'renewal' => round($renewalPercentage, 2),  // Renewal scholars percentage
            'assigned' => round($assignedPercentage, 2), // Assigned scholars percentage
        ]);
    }
    function getNearlyFinishedandFinishedScholar()
    {
        include "connection.php";

        // SQL query to get the time_in and time_out values
        $sql = "
    SELECT 
        stud_active_id, 
        assign_stud_id, 
        dtr_assign_id, 
        c.dtr_current_time_in, 
        c.dtr_current_time_out
    FROM tbl_activescholars AS a
    LEFT JOIN tbl_assign_scholars AS b ON b.assign_stud_id = a.stud_active_id
    LEFT JOIN tbl_dtr AS c ON c.dtr_assign_id = b.assign_id";

        // Execute the query
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Variables to count the number of nearly finished and finished scholars
        $finishedCount = 0;
        $nearlyFinishedCount = 0;
        $totalCount = 0;

        // Loop through the results and calculate the time difference
        foreach ($results as $row) {
            $totalCount++;

            // Get the time_in and time_out as DateTime objects
            $timeIn = new DateTime($row['dtr_current_time_in']);
            $timeOut = new DateTime($row['dtr_current_time_out']);

            // Calculate the difference in minutes
            $interval = $timeIn->diff($timeOut);
            $workedMinutes = ($interval->h * 60) + $interval->i; // Convert hours to minutes and add the minutes

            // Check if the scholar is "Finished" or "Nearly Finished"
            if ($workedMinutes >= 180 && $workedMinutes >= 90) {
                $finishedCount++;
            } elseif ($workedMinutes < 180) {
                $nearlyFinishedCount++;
            }
        }

        // Calculate percentages for Finished and Nearly Finished scholars
        $finishedPercentage = $totalCount > 0 ? ($finishedCount / $totalCount) * 100 : 0;
        $nearlyFinishedPercentage = $totalCount > 0 ? ($nearlyFinishedCount / $totalCount) * 100 : 0;

        // Return the result as a JSON object
        echo json_encode([
            'finished' => round($finishedPercentage, 2),        // Percentage of finished scholars
            'nearlyFinished' => round($nearlyFinishedPercentage, 2), // Percentage of nearly finished scholars
        ]);
    }

    function getAllStudentFacilitator()
    {
        include "connection.php";

        $sql = "SELECT c.assign_stud_id, a.stud_name, d.dutyH_name, f.sub_room, g.supM_name, c.assign_render_status, c.assign_evaluation_status
                FROM tbl_scholars AS a
                LEFT JOIN tbl_activescholars AS b ON b.stud_active_id = a.stud_id
                INNER JOIN tbl_assign_scholars AS c ON c.assign_stud_id = b.stud_active_id
                INNER JOIN tbl_duty_hours AS d ON d.dutyH_id = c.assign_duty_hours_id
                INNER JOIN tbl_office_master AS e ON e.off_id = c.assign_office_id
                INNER JOIN tbl_subjects AS f ON f.sub_id = e.off_subject_id
                INNER JOIN tbl_supervisors_master AS g ON g.supM_id = f.sub_supM_id";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return json_encode($result);
    }
    function getAllOfficeScholar()
    {
        include "connection.php";

        $sql = "SELECT assign_stud_id, stud_name, dutyH_name, build_name, supM_name, assign_render_status, assign_evaluation_status
                FROM tbl_assign_scholars AS a
                LEFT JOIN tbl_activescholars AS b ON b.stud_active_id = a.assign_stud_id
                INNER JOIN tbl_scholars AS c ON c.stud_id = b.stud_active_id
                INNER JOIN tbl_duty_hours AS d ON d.dutyH_id = a.assign_duty_hours_id
                INNER JOIN tbl_office_master AS e ON e.off_id = a.assign_office_id
                INNER JOIN tbl_office_type AS f ON f.offT_id = e.off_type_id
                INNER JOIN tbl_department AS g ON g.dept_id = f.offT_dept_id
                INNER JOIN tbl_building AS h ON h.build_id = g.dept_build_id
                INNER JOIN tbl_supervisors_master AS j ON j.supM_id = f.offT_supM_id";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return json_encode($result);
    }

    function getScholarAllAvailableSchedule($json){
        include "connection.php";
        $json = json_decode($json, true);
        $sql = "SELECT * FROM tbl_ocr";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        return json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    

    function getAllSubjects(){
        include "connection.php";
        $sql = "SELECT a.sub_code, a.sub_descriptive_title, a.sub_section, a.sub_room, a.sub_time, b.day_name AS f2f_day, d.day_name AS rc_day, learning_name, a.sub_used
                FROM tbl_subjects AS a
                INNER JOIN tbl_day AS b ON b.day_id = a.sub_day_f2f_id
                INNER JOIN tbl_learning_modalities AS c ON c.learning_id = a.sub_learning_modalities_id
                INNER JOIN tbl_day AS d ON d.day_id = a.sub_day_rc_id";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return json_encode($result);
    }
}

$json = isset($_POST["json"]) ? $_POST["json"] : "0";
$operation = isset($_POST["operation"]) ? $_POST["operation"] : "0";
$transaction = new Transaction();

switch ($operation) {
    case "getStudentsDetailsAndStudentDutyAssign":
        echo $transaction->getStudentsDetailsAndStudentDutyAssign($json);
        break;
    case "getAssignedScholars":
        echo $transaction->getAssignedScholars($json);
        break;
    case "getStudentDtr":
        echo $transaction->getStudentDtr($json);
        break;
    case "studentsAttendance":
        echo $transaction->studentsAttendance($json);
        break;
    case "submitStudentEvaluation":
        echo $transaction->submitStudentFacilitatorEvaluation($json);
        break;
    case "getStudentDutyData":
        echo $transaction->getStudentDutyData();
        break;
    case "getActiveAndAssignedScholars":
        echo $transaction->getActiveAndAssignedScholars();
        break;
    case "getActiveAndRenewedScholars":
        echo $transaction->getActiveAndRenewedScholars();
        break;
    case "getNearlyFinishedandFinishedScholar":
        echo $transaction->getNearlyFinishedandFinishedScholar();
        break;
    case "getAllStudentFacilitator":
        echo $transaction->getAllStudentFacilitator();
        break;
    case "getAllOfficeScholar":
        echo $transaction->getAllOfficeScholar();
        break;
    case "getScholarAllAvailableSchedule":
        echo $transaction->getScholarAllAvailableSchedule($json);
        break;
    case "getAllSubjects":
        echo $transaction->getAllSubjects();
        break;
    default:
        echo json_encode(["error" => "Invalid operation"]);
        break;
}

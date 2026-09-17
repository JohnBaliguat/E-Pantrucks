<?php
session_start();
include "php/config/config.php";
require_once "php/helpers/auth.php";

if (isset($_POST["login-btn"])) {
    function validate($data)
    {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return $data;
    }

    $uname = validate($_POST["uname"]);
    $pass = validate($_POST["pass"]);

    if (empty($uname)) {
        header("Location: login?error=Username is required");
        exit();
    } elseif (empty($pass)) {
        header("Location: login?error=Password is required");
        exit();
    } else {
        // Check user table
        $sql =
            'SELECT user_id, "user_idNumber", user_name, user_fname, user_lname, user_mname, user_email, user_pass, user_type, user_image, "user_accountStat", user_code FROM "user" WHERE user_name = ?';
        $stmt = $conn->prepare($sql);
        $stmt->execute([$uname]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $hashedPassword = $row["user_pass"];

            if ($row["user_accountStat"] === "Inactive") {
                header(
                    "Location: login?error=Account is inactive. Please contact administrator",
                );
                exit();
            }

            if (password_verify($pass, $hashedPassword)) {
                setUserSession($row);
                logUserLogin($conn, $row);

                header("Location: dashboard");
                exit();
            } else {
                logFailedLogin($conn, $uname);
                header("Location: login?error=Incorrect username or password");
                exit();
            }
        } else {
            logFailedLogin($conn, $uname);
            header("Location: login?error=Incorrect username or password");
            exit();
        }
    }
}
?>

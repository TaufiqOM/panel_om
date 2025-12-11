<?php
@session_start();

class SessionManager {
    
    public static function checkEmployeeSession() {
        if (isset($_SESSION['employee_logged_in']) && $_SESSION['employee_logged_in'] === true) {
            return [
                'logged_in' => true,
                'employee' => $_SESSION['employee_data']
            ];
        }
        return ['logged_in' => false];
    }
    
    public static function createEmployeeSession($employeeData) {
        $_SESSION['employee_logged_in'] = true;
        $_SESSION['employee_data'] = $employeeData;
        $_SESSION['last_activity'] = time();
    }
    
    public static function destroyEmployeeSession() {
        $_SESSION['employee_logged_in'] = false;
        unset($_SESSION['employee_data']);
        unset($_SESSION['last_activity']);
        session_destroy();
    }
    
    public static function checkSessionTimeout($timeoutMinutes = 480) {
        if (isset($_SESSION['last_activity'])) {
            $timeout = $timeoutMinutes * 60;
            if (time() - $_SESSION['last_activity'] > $timeout) {
                self::destroyEmployeeSession();
                return false;
            }
        }
        $_SESSION['last_activity'] = time();
        return true;
    }
}
?>
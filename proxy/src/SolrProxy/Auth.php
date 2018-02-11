<?php
namespace DIQA\SolrProxy;

class Auth {

    /**
     *
     * @return a list of groups for the currently logged-in user
     */
    public static function session() {
        session_start();

        if (Auth::checkLogout()) {
            @session_destroy();
            exit();
        }

        global $wgDBname;
        $userid = self::getCookie($wgDBname . 'UserID');
        $userName = self::getCookie($wgDBname . 'UserName');

        // access Wiki once to retrieve user groups and store it in a proxy-session
        if (count(self::getSession('user_groups' . $userid)) === 0) {
            $_SESSION['user_groups' . $userid] = [];

            $sessionId = self::getCookie($wgDBname . '_session');
            $cookies = [
                $wgDBname . 'UserID' => $userid,
                $wgDBname . 'UserName' => $userName,
                $wgDBname . '_session' => $sessionId
            ];

            global $wgServer, $wgScriptPath;
            $res = self::http("$wgServer$wgScriptPath/api.php?action=diqa_util_userdataapi&format=json", $cookies);
            
            $o = json_decode($res[2]);
            
            if (isset($o->result)) {
                $groups = isset($o->result->user_groups) ? $o->result->user_groups : [];
                $_SESSION['user_groups' . $userid] = $groups;
            } else {
                @session_destroy();
                throw new \Exception("Not logged in.");
            }
            
        }

        return $_SESSION['user_groups' . $userid];
    }

    /**
     *
     * @return true iff the user is logged in
     */
    public static function isLoggedIn() {
        try {
            Auth::session();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function checkLogout() {
        $parts = parse_url($_SERVER['REQUEST_URI']);
        $query = $parts['query'];
        $keyValues = explode("&", $query);
        $params = [];

        foreach ($keyValues as $keyValue) {
            list ($key, $value) = explode("=", $keyValue);
            $params[$key] = urldecode($value);
        }

        return isset($params['logout']);
    }

    /**
     * calls the URL
     *
     * @return array with
     *         $header
     *         $status
     *         $res (json-encoded)
     */
    private static function http($url, $cookies) {
        $res = "";
        $header = "";
        global $wgDBname;

        // Create a curl handle to a non-existing location
        $ch = curl_init($url);

        $cookieArray = [];
        foreach ($cookies as $key => $value) {
            $cookieArray[] = "$key=$value";
        }

        // Execute
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_COOKIE, implode('; ', $cookieArray));
        $res = curl_exec($ch);

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $bodyBegin = strpos($res, "\r\n\r\n");
        list ($header, $res) = $bodyBegin !== false ? array(
            substr($res, 0, $bodyBegin),
            substr($res, $bodyBegin + 4)
        ) : array(
            $res,
            ""
        );
        return array(
            $header,
            $status,
            str_replace("%0A%0D%0A%0D", "\r\n\r\n", $res)
        );
    }

    private static function getCookie($var) {
        if (isset($_COOKIE[$var])) {
            return $_COOKIE[$var];
        }
        return '';
    }

    private static function getSession($var) {
        if (isset($_SESSION[$var])) {
            return $_SESSION[$var];
        }
        return [];
    }
}
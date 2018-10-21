<?php

require_once 'config.php';


if (!isset($_GET["f"])) {
    die("Error: Function not specified.");
}


function get_messages($db, $timestamp) {
    if($query = $db->prepare("SELECT `id`, `user_id`, `type`, `content`, `time` FROM `messages` WHERE `time` > ?")) {
        // Bind the variables and execute
        // "s" for string
        $query->bind_param("s", $timestamp);
        $query->execute();
        // Bind results to variables
        $query->bind_result($id, $user_id, $type, $content, $time);
        $arr = array();
        while($query->fetch()) {
            // Append each user
            $arr[] = array("id" => $id, "user_id" => $user_id, "type" => $type, "content"=> $content, "time" => $time);
        }
        $query->close();
        return $arr;
    } else {
        return generate_error_array("2", "Database error.");
    }
}


function get_users($db) {
    if($query = $db->prepare("SELECT `id`, `username`, `display_name`, `key` FROM `users` WHERE 1")) {
        $query->execute();
        // Bind results to variables
        $query->bind_result($id, $username, $display_name, $key);
        $arr = array();
        while($query->fetch()) {
            // Append each user
            $arr[] = array("id" => $id, "username" => $username, "display_name" => $display_name, "key"=> $key);
        }
        $query->close();
        return $arr;
    } else {
        return generate_error_array("2", "Database error.");
    }
}


function get_user_info($db, $username) {
    if($query = $db->prepare("SELECT `id`, `username`, `display_name`, `key` FROM `users` WHERE `username` = ?")) {
        $query->bind_param("s", $username);
        $query->execute();
        // Bind results to variables
        $query->bind_result($id, $username, $display_name, $key);
        if (!($query->fetch())) {
            // Results are empty.
            $query->close();
            return array();
        } else {
            // Generate the array and close the db.
            $arr[] = array("id" => $id, "username" => $username, "display_name" => $display_name, "key"=> $key);
            $query->close();
            return $arr[0];
        }
    } else {
        return generate_error_array("2", "Database error.");
    }
}


function generate_key() {
    // Rand is inclusive.
    $raw = strval(rand(0, 9999));
    // Pad with 0s if < 1000.
    return str_repeat("0", 4 - strlen($raw)) . $raw;
}


function is_user_in_db($db, $username) {
    if($query = $db->prepare("SELECT * FROM `users` WHERE `username` = ?")) {
        $query->bind_param("s", $username);
        $query->execute();
        $query->store_result();
        if ($query->num_rows > 0) {
            $query->close();
            return true;
        } else {
            $query->close();
            return false;
        }
    } else {
        return NULL;
    }
}


function register_new_user($db, $username) {
    // Check user not in db
    if (is_user_in_db($db, $username)) {
        return generate_error_array("1", "User already exists.");
    } else {
        $key = generate_key();

        if($query = $db->prepare("INSERT INTO `users` (`username`, `display_name`, `key`) VALUES (?, ?, ?)")) {
            $query->bind_param("sss", $username, $username, $key);
            $success = $query->execute();
            $query->close();
            if ($success) {
                return generate_success_array($key);
            } else {
                return generate_error_array("3", "Database error.");
            }
        } else {
            return generate_error_array("2", "Database error.");
        }
    }
}


function change_display_name($db, $username, $key, $display_name) {
    $user = get_user_info($db, $username);
    if (count($user) == 0) {
        return generate_error_array("2", "User not found.");
    } else if ($user["key"] !== $key) {
        return generate_error_array("3", "Invalid key.");
    } else {
        // User and key valid.
        if($query = $db->prepare("UPDATE `users` SET `display_name` = ? WHERE `id` = ?")) {
            $query->bind_param("ss", $display_name, $user["id"]);
            $success = $query->execute();
            $query->close();
            if ($success) {
                return generate_success_array("Display name changed.");
            } else {
                return generate_error_array("5", "Database error.");
            }
        } else {
            return generate_error_array("4", "Database error.");
        }
    }
}


function send_message($db, $username, $key, $message) {
    $user = get_user_info($db, $username);
    if (count($user) == 0) {
        return generate_error_array("2", "User not found.");
    } else if ($user["key"] !== $key) {
        return generate_error_array("3", "Invalid key.");
    } else {
        // User and key valid.
        if($query = $db->prepare("INSERT INTO `messages` (`user_id`, `type`, `content`) VALUES (?, ?, ?)")) {
            $message_type = "text";
            $query->bind_param("sss", $user["id"], $message_type, $message);
            $success = $query->execute();
            $query->close();
            if ($success) {
                return generate_success_array("Message successfully sent.");
            } else {
                return generate_error_array("5", "Database error.");
            }
        } else {
            return generate_error_array("4", "Database error.");
        }
    }
}


function output_json($var) {
    header('Content-Type: application/json');
    echo json_encode($var);
}


function generate_error_array($code, $message) {
    return array(
        "success" => "0",
        "error_code" => $code,
        "message" => $message
    );
}

function generate_success_array($message) {
    return array(
        "success" => "1",
        "message" => $message
    );
}


// Process page requests

$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);


// if (mysqli_connect_errno())
if ($db->connect_errno) {
    die("Error: Could not connect to database.");
} else {
    // f1: read
    // f2: register
    // f3: send
    if ($_GET["f"] == 1) {
        // Get messages.
        // ?f=1
        // ?f=1&time={yyyy-mm-dd hh:mm:ss}
        $current_time = "";
        if (isset($_GET["time"])) {
            $current_time = date($_GET["time"]);
        } else {
            // $current_time = date("Y-m-d G:i:s");
            $current_time = date("0000-00-00 00:00:00");
        }
        $messages = get_messages($db, $current_time);
        output_json($messages);
    } else if ($_GET["f"] == 2) {
        // Register new user.
        // Returns key.
        // ?f=2&user={username}
        if (isset($_GET["user"])) {
            $result = register_new_user($db, $_GET["user"]);
            output_json($result);
        } else {
            output_json(generate_error_array("1", "Username not specified."));
        }
    } else if ($_GET["f"] == 3) {
        // Send message.
        // ?f=3&user={username}&key={key}&msg={contents}
        if (isset($_GET["user"]) and isset($_GET["key"]) and isset($_GET["msg"])) {
            $result = send_message($db, $_GET["user"], $_GET["key"], $_GET["msg"]);
            output_json($result);
        } else {
            output_json(generate_error_array("1", "Invalid request."));
        }
    } else if ($_GET["f"] == 4) {
        // Change display name.
        // ?f=4&user={username}&key={key}&nick={display_name}
        if (isset($_GET["user"]) and isset($_GET["key"]) and isset($_GET["nick"])) {
            $result = change_display_name($db, $_GET["user"], $_GET["key"], $_GET["nick"]);
            output_json($result);
        } else {
            output_json(generate_error_array("1", "Invalid request."));
        }
    }
    /*
    else if ($_GET["f"] == 136) {
        // Secret: Get user info.
        if (isset($_GET["user"])) {
            $user = get_user_info($db, $_GET["user"]);
            if (count($user) > 0) {
                output_json($user);
            } else {
                output_json(generate_error_array("3", "User not found."));
            }
        } else {
            output_json(generate_error_array("1", "Username not specified."));
        }
    } else if ($_GET["f"] == 137) {
        // Secret: List all users.
        $users = get_users($db);
        output_json($users);
    }
    */
}

$db->close();

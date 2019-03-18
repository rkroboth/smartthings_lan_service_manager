<?php


$min_uptime = 300;  // don't do anything in the first n seconds of uptime
$watchdog_ping_file = "/tmp/service_manager_watchdog_ping";
$restart_after = 60;  // restart this many seconds after last ping detected
$stop_restarting_after = $restart_after + 3600;



// don't do anything in the first n minutes of uptime
$uptime = `/usr/bin/uptime -s`;
$uptime = strtotime($uptime);
if (!$uptime){
    print "could not read uptime\n";
    exit;
}
if (time() - $uptime < $min_uptime){
    print "system has only been up for " . (time() - $uptime) . " seconds\n";
    exit;
}

$filemtime = filemtime($watchdog_ping_file);

if (
    time() > $filemtime + $restart_after
    && time() < $filemtime + $stop_restarting_after
){
    try {
        $pi = pathinfo(__FILE__);
        $lib_path = $pi['dirname'];
        $subject = "Smart Things LanMan has stopped running, restarting...";
        print $subject . "\n";
        $body = $subject;
        $result = send_email($subject, $body, $lib_path);
    } catch (Exception $e){
    }

    // do the reboot
    `/sbin/shutdown -r now`;
}
else {
    print "Nothing to do\n";
}
exit;


function send_email($subject, $body, $lib_path){

    $to = "rusty@krobeinteractive.com";
    $from = "krobeinteractivemail@gmail.com";
    $from_friendly_name = "Smartthings LanMan Raspi";
    $smtp_server = "smtp.gmail.com";
    $smtp_username = "krobeinteractivemail@gmail.com";
    $smtp_password = "fvoxvytvgthhrjji";
    $smtp_port = 587;
    $smtp_encryption = "tls";

    if (!class_exists("PHPMailer")){
        if (!$lib_path){
            throw new Exception("PHPMailer library has not been included; please include the PHPMailer library, or pass the lib_path parameter so the function can do so");
        }

        $phpmailer_lib_path = $lib_path . "/phpmailer";
        if (!file_exists($phpmailer_lib_path)){
            throw new Exception("PHPMailer library not found at " . $phpmailer_lib_path);
        }
        require_once $phpmailer_lib_path . '/PHPMailerAutoload.php';
    }

    $mail = new PHPMailer;
    $mail->isSMTP();
    $mail->Host = $smtp_server;

    $mail->SMTPAuth = true;
    $mail->Username = $smtp_username;
    $mail->Password = $smtp_password;

    if ($smtp_encryption){
        $mail->SMTPSecure = $smtp_encryption;
    }

    $mail->Port = (int)$smtp_port;

    $mail->setFrom($from, $from_friendly_name);

    $mail->addAddress($to);
    $mail->Subject = $subject;

    $mail->isHTML(false);
    $mail->Body = $body;

    $hostname = gethostname();
    if (!$hostname){
        $hostname = trim(`hostname`);
    }
    $message_id = "msg_sent_at_" . round(microtime(true) * 1000) . "@" . $hostname;
    $mail->MessageID = $message_id;

    $result = $mail->send();
    if ($result){
        return array(
            "success" => true,
        );
    }

    $error_info = $mail->ErrorInfo;
    if ($error_info){
        $error_info = "Could not send smtp email: " . $error_info;
    }
    else {
        $error_info = "Could not send smtp email for unknown reason";
    }
    return array(
        "success" => false,
        "error_msg" => $error_info,
    );

}


<?php
/**
 * @package public.
 * @author Supme
 * @copyright Supme 2014
 * @license http://opensource.org/licenses/MIT MIT License	
 *
 *  THE SOFTWARE AND DOCUMENTATION ARE PROVIDED "AS IS" WITHOUT WARRANTY OF
 *	ANY KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
 *	IMPLIED WARRANTIES OF MERCHANTABILITY AND/OR FITNESS FOR A PARTICULAR
 *	PURPOSE.
 *
 *	Please see the license.txt file for more information.
 *
 */
include_once 'config.php';
include_once 'libs/PHPMailer/PHPMailerAutoload.php';

$db = new PDO('mysql:host='.$config['dbHost'].';dbname='.$config['dbName'].';charset=UTF8', $config['dbUser'], $config['dbPass']);

$query = $db->prepare('SELECT formId, photoId, fname, mname, lname, gender, email FROM people WHERE send IS NULL AND NOT email IS NULL');
$query->execute();
$peoples = $query->fetchAll();

foreach($peoples as $people){
    $query = $db->prepare('SELECT name FROM photo WHERE photoId = ?');
    $query->execute([$people['photoId']]);
    $photo = $query->fetchAll();

    $mail = new PHPMailer();

    $mail->isSMTP();
    $mail->Host = $config['mailHost'];
    $mail->Port = $config['mailPort'];
    $mail->SMTPAuth = $config['mailSMTPAuth'];
    $mail->Username = $config['mailUsername'];
    $mail->Password = $config['mailPassword'];
    $mail->SMTPSecure = $config['mailSMTPSecure'];
    $mail->From = $config['mailFrom'];
    $mail->FromName = $config['mailFromName'];
    $mail->addReplyTo($config['mailFrom'], $config['mailFromName']);
    $mail->addAddress($people['email'], $people['fname']);

    foreach($photo as $poster){
        $mail->addAttachment($config['photo'].DIRECTORY_SEPARATOR.$people['photoId'].'_'.$poster['name'].'.jpg');
    }

    $mail->isHTML(true);
    $mail->Subject = 'Here is you photo in posters!';
    $mail->Body    = 'Привет, буфет!<br/>'.$people['fname'].' '.$people['mname'].' '.$people['lname'].' Ваши постеры во вложении.';
    $mail->AltBody = 'Это для непонимающих HTML клиентов.';

    if($mail->send()) {
        $query = $db->prepare('UPDATE people SET send = NOW() WHERE formId = ?');
        $query->execute([$people['formId']]);
        echo "send ok ";
    } else {
        echo "error ".$mail->ErrorInfo." ";
    }
    echo "Id:".$people['formId']." for: ".$people['email']." at ".date(DATE_RFC822)."\n";

}

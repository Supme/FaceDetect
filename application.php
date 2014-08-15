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

class application {

    public function __construct($config){
        $this->config = $config;
    }

    public function sendForm(){
        $this->db = new PDO('mysql:host='.$this->config['dbHost'].';dbname='.$this->config['dbName'].';charset=UTF8', $this->config['dbUser'], $this->config['dbPass']);

        $query = $this->db->prepare('SELECT photoId, fname, mname, lname, gender, email FROM people WHERE formId = ?');
        $query->execute([$_POST['formId']]);
        $people = $query->fetch();

        $form = [
            $_POST['fname'],
            $_POST['mname'],
            $_POST['lname'],
            $_POST['gender'],
            $_POST['email'],
            $_POST['formId'],
        ];

        $query = $this->db->prepare('UPDATE people SET fname = ?, mname = ?, lname = ?, gender = ?, email = ? WHERE formId = ?');
        $query->execute($form);

        $query = $this->db->prepare('SELECT name FROM photo WHERE photoId = ?');
        $query->execute([$people['photoId']]);
        $photo = $query->fetchAll();

        $mail = new PHPMailer();

        $mail->isSMTP();                                      // Set mailer to use SMTP
        $mail->Host = 'kladr.biz';  // Specify main and backup SMTP servers
        $mail->Port = 587;
        $mail->SMTPAuth = true;                               // Enable SMTP authentication
        $mail->Username = 'test@dmbasis.email';                 // SMTP username
        $mail->Password = '';                           // SMTP password
        $mail->SMTPSecure = 'tls';                            // Enable encryption, 'ssl' also accepted

        $mail->From = 'test@dmbasis.email';
        $mail->FromName = 'Picachu';
        $mail->addAddress($_POST['email'], $_POST['fname']);     // Add a recipient
        $mail->addReplyTo('test@dmbasis.email', 'Picachu');


        foreach($photo as $poster){
            $mail->addAttachment($this->config['photo'].DIRECTORY_SEPARATOR.$people['photoId'].'_'.$poster['name'].'.jpg');
        }

        $mail->isHTML(true);                                  // Set email format to HTML
        $mail->Subject = 'Here is you photo in posters!';
        $mail->Body    = 'Привет, буфет!<br/>'.$_POST['fname'].' '.$_POST['mname'].' '.$_POST['lname'].' Ваши постеры во вложении.';
        $mail->AltBody = 'Это для непонимающих HTML клиентов.';

        if(!$mail->send()) {
            echo 'Mailer Error: ' . $mail->ErrorInfo;
        }

        return true;
    }

    // Фотоснимок
    public function flashFace(){

        $formId = $this->getRandom(5);

        $photoId = $this->getRandom(10);

        // Запись в базу начальных значений
        $this->db = new PDO('mysql:host='.$this->config['dbHost'].';dbname='.$this->config['dbName'].';charset=UTF8', $this->config['dbUser'], $this->config['dbPass']);
        $query = $this->db->prepare('INSERT INTO people (formId, photoId) VALUES (?, ?)');
        $query->execute([$formId, $photoId]);

        // Наделаем постеров
        $this->getImage($this->config['photo'].DIRECTORY_SEPARATOR.$photoId.'.jpg');

        // Автоматически определяем морду лица, вырезаем, расширяем, поворачиваем
        $face = $this->cropImage($this->config['photo'].DIRECTORY_SEPARATOR.$photoId.'.jpg');
        //imagejpeg($face, $this->config['photo'].DIRECTORY_SEPARATOR.$photoId.'_autocrop.jpg');

        $masks = $this->getMasks();

        foreach($masks as $name => $mask){
            $image = $this->createImage(
                $face, // detected face
                $mask['file'], // poster for face
                $mask['width'], // width face
                $mask['angle'], //angle face
                $mask['x'], // x position face
                $mask['y'] // y position face
            );
            imagejpeg($image, $this->config['photo'].DIRECTORY_SEPARATOR.$photoId.'_'.$name.'.jpg');

            $query = $this->db->prepare('INSERT INTO photo (photoId, name) VALUES (?, ?)');
            $query->execute([$photoId, $name]);
        }

        return $formId;
    }

    // Получаем список масок, беря параметры из названия файлов
    private function getMasks(){
        // Если есть файл с кэшем масок и их параметров
        $cacheFile = $this->config['masks'].DIRECTORY_SEPARATOR.'masks.json';
        if (is_readable($cacheFile)){
            // ...тогда берем его
            $masks = json_decode(file_get_contents($cacheFile), true);
        } else {
            // ...нет, значит создадим его
            $masks = [];
            foreach(glob($this->config['masks'].DIRECTORY_SEPARATOR.'*png') as $file){
                $params = explode('_', $file);
                $dbg[] = $file;
                if(count($params) == 5){
                    $masks[str_replace($this->config['masks'].DIRECTORY_SEPARATOR, '', $params[0])] = [
                        'file' => $file,
                        'width' => (int)$params[1],
                        'angle' => (int)$params[2],
                        'x' => (int)$params[3],
                        'y' => (int)$params[4]
                    ];
                }

            };
            //file_put_contents('debug.txt', var_export($dbg,true).var_export($masks,true));
            file_put_contents($cacheFile, json_encode($masks));
        }

        return $masks;
    }

    // Проверка на наличие подходящего письма
    public function isFace(){
        $result = false;
        $tmpFile = $this->config['tmp_dir'].DIRECTORY_SEPARATOR.$this->config['sessionId'].'.jpg';
        $this->getImage($tmpFile);
        if ($face = $this->faceDetect($tmpFile)){
            // Если морда достаточно высока и широка, она нам подходит
            if($face['w'] > $this->config['faceWidth']/100 and $face['h'] > $this->config['faceHeight']/100){
                $result = true;
            }
        }
        return $result;
    }

    // Получаем от клиента картинку
    private function getImage($file){
        $data = $_POST['imgBase64'];
        $uri =  substr($data,strpos($data,",")+1);
        file_put_contents($file, base64_decode($uri));
    }

    // Нахождение лица с помощью OpenCV
    private function faceDetect($file){
        $face = exec('./bin/find-face --cascade=./cascade/haarcascade_frontalface_default.xml '.$file);
        // Если OpenCV отдал нам 4 значения, значит он выполнился удачно
        if(count($faceCoor = explode(' ', $face)) == 4){
            $result['x'] = $faceCoor[0];
            $result['y'] = $faceCoor[1];
            $result['w'] = $faceCoor[2];
            $result['h'] = $faceCoor[3];
        } else {
            $result = false;
        }

        return $result;
    }

    // Находим лицо на картинке и вырезаем его
    private function cropImage($file){
        $detect = $this->faceDetect($file);

        $image = imagecreatefromjpeg($file);

        $x = imagesx($image);
        $y = imagesy($image);
        //Реально определенное
        $fromX = $x * $detect['x'];
        $fromY = $y * $detect['y'];
        $detectWidth = $x * $detect['w'];
        $detectHeight = $y * $detect['h'];
        $toX = $fromX + $detectWidth;
        $toY = $fromY + $detectHeight;
        //Увеличим высоту, чтоб добавить лоб и подбородок, который обычно обрезается в OpenCV
        $fromY = $fromY - ($detectHeight * 0.10);
        $toY = $toY + ($detectHeight * 0.20);

        // Вырезаем лицо из картинки по полученым координатам
        $width = $toX - $fromX;
        $height = $toY - $fromY;
        $face = imagecreatetruecolor($width, $height);
        imagecopyresampled($face, $image, 0, 0, $fromX, $fromY, $width, $height, $width, $height);

        return $face;
    }

    // Вставка лица в постер
    private function createImage($image, $poster, $width, $angle, $positionX, $positionY){

        // Ресайзим с сохранением пропорций
        $height = imagesy($image) * ((int)$width/imagesx($image));
        $imageTmp = imagecreatetruecolor((int)$width, $height);
        imagecopyresampled($imageTmp, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));

        // Поворачиваем
        $image = imagerotate($imageTmp, (int)$angle, hexdec('FFFFFF'));

        // Берем постер
        $imagePoster = imagecreatefrompng($poster);
        // Сохраняем альфа-канал.
        imagesavealpha($imagePoster, true);

        // ... пустой хост размером с постер
        $imageTmp = imagecreatetruecolor(imagesx($imagePoster), imagesy($imagePoster));

        // Накладываем на пустой холст морду нужным смещением
        imagecopy(
            $imageTmp,
            $image,
            (int)$positionX,
            (int)$positionY,
            0,
            0,
            imagesx($image),
            imagesy($image)
        );

        // ... и на все это сверху кидаем постер
        imagecopy(
            $imageTmp,
            $imagePoster,
            0,
            0,
            0,
            0,
            imagesx($imagePoster),
            imagesy($imagePoster)
        );

        return $imageTmp;
    }

    // Генератор случайных строк
    static public function getRandom($size){
        $chars="qazxswedcvfrtgbnhyujmkiolp1234567890QAZXSWEDCVFRTGBNHYUJMKIOLP";
        $lenght=StrLen($chars)-1;
        $result=null;
        while($size--)
            $result.=$chars[rand(0,$lenght)];

        return $result;
    }
}

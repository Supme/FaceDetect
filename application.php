<?php
/**
 * @package Photo kiosk.
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
        $result['status'] = 'success';
        $result['message'] = [];

        $form['fname'] = htmlspecialchars($_POST['fname']);
        if($form['fname'] != $_POST['fname'] or strlen($form['fname']) < 2){
            $result['status'] = 'error';
            $result['message'][] = 'Not valid fname value: "'.$_POST['fname'].'"';
        }

        $form['mname'] = htmlspecialchars($_POST['mname']);
        if($form['mname'] != $_POST['mname'] or strlen($form['mname']) < 2){
            $result['status'] = 'error';
            $result['message'][] = 'Not valid mname value: "'.$_POST['mname'].'"';
        }

        $form['lname'] = htmlspecialchars($_POST['lname']);
        if($form['lname'] != $_POST['lname'] or strlen($form['lname']) < 2){
            $result['status'] = 'error'; $l = strlen($form['lname']);
            $result['message'][] = 'Not valid lname value: "'.$_POST['lname'].'" - "'.$form['lname'].'" - "'.$l.'"';
        }

        $form['gender'] = $_POST['gender'];
        if(strlen($form['gender']) != 1){
            $result['status'] = 'error';
            $result['message'][] = 'Not valid gender value: "'.$_POST['gender'].'"';
        }

        if(!($form['email'] = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL))){
            $result['status'] = 'error';
            $result['message'][] = 'Not valid email value: "'.$_POST['email'].'"';
        }

        $form['formId'] = htmlspecialchars($_POST['formId']);
        if($form['formId'] != $_POST['formId']  or strlen($form['formId']) != 5){
            $result['status'] = 'error';
            $result['message'][] = 'Not valid formId value: "'.$_POST['formId'].'"';
        }
/*
        $form = [
            'fname' => $_POST['fname'],
            'mname' => $_POST['mname'],
            'lname' => $_POST['lname'],
            'gender' => $_POST['gender'],
            'email' => $_POST['email'],
            'formId' => $_POST['formId'],
        ];
*/
        if($result['status'] != 'error')
        {
            $this->db = new PDO('mysql:host='.$this->config['dbHost'].';dbname='.$this->config['dbName'].';charset=UTF8', $this->config['dbUser'], $this->config['dbPass']);

            $query = $this->db->prepare('SELECT photoId, fname, mname, lname, gender, email, formId FROM people WHERE formId = ?');
            $query->execute([$form['formId']]);
            $people = $query->fetch();

            if($people['formId'] != $form['formId']){
                $result['status'] = 'error';
                $result['message'][] = 'Form Id not found';
            }

            if($people['email'] != NULL){
                $result['status'] = 'error';
                $result['message'][] = 'Form ready';
            }
        }

        if($result['status'] != 'error')
        {
            $query = $this->db->prepare('UPDATE people SET fname = ?, mname = ?, lname = ?, gender = ?, email = ? WHERE formId = ?');
            $query->execute([$form['fname'], $form['mname'], $form['lname'], $form['gender'], $form['email'], $form['formId']]);

        }

        return $result;
    }

    // Фотоснимок
    public function flashFace(){

        // ToDo Надеюсь повторяющиеся не сгенерятся, поэтому не проверяю на существование, хотя может сделать запрос к базе?
        $formId = $this->getRandom(5);
        $photoId = $this->getRandom(10);

        // Запись в базу начальных значений
        $this->db = new PDO('mysql:host='.$this->config['dbHost'].';dbname='.$this->config['dbName'].';charset=UTF8', $this->config['dbUser'], $this->config['dbPass']);
        $query = $this->db->prepare('INSERT INTO people (formId, photoId) VALUES (?, ?)');
        $query->execute([$formId, $photoId]);

        /*
         *  Наделаем постеров
         */
        $this->getImage($this->config['photo'].DIRECTORY_SEPARATOR.$photoId.'.jpg');
        // Автоматически определяем морду лица, вырезаем, расширяем, поворачиваем
        $face = $this->cropImage($this->config['photo'].DIRECTORY_SEPARATOR.$photoId.'.jpg');

        // Получим список и конфигурацию масок
        $masks = $this->getMasks();

        foreach($masks as $name => $mask){
            $image = $this->createImage(
                $face, // detected face
                $mask['file'], // poster for face
                $mask['width'], // width face
                $mask['angle'], //angle face
                $mask['x'], // x position face
                $mask['y'], // y position face
                $mask['gamma'], // gamma correction
                $mask['r'], // r  correction
                $mask['g'], // g  correction
                $mask['b'], //  b correction
                $mask['bw'] //  greyscale
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
        $cacheFile = $this->config['masks'].DIRECTORY_SEPARATOR.'.masks.json';
        if (is_readable($cacheFile)){
            // ...тогда берем его
            $masks = json_decode(file_get_contents($cacheFile), true);
        } else {
            // ...нет, значит создадим его
            $masks = [];
            foreach(glob($this->config['masks'].DIRECTORY_SEPARATOR.'*png') as $file){
                $params = explode('_', $file);
                $dbg[] = $file;
                if(count($params) == 11){
                    $masks[str_replace($this->config['masks'].DIRECTORY_SEPARATOR, '', $params[0])] = [
                        'file' => $file,
                        'width' => (int)$params[1],
                        'angle' => (int)$params[2],
                        'x' => (int)$params[3],
                        'y' => (int)$params[4],
                        'gamma' => (float)$params[5],
                        'r' => (int)$params[6],
                        'g' => (int)$params[7],
                        'b' => (int)$params[8],
                        'bw' => (int)$params[9]
                    ];
                }
            };

            file_put_contents($cacheFile, json_encode($masks));
        }

        return $masks;
    }

    // Проверка на наличие подходящего лица
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
        imagegammacorrect($image, 1.0, 1+$this->config['gamma']);

        return $face;
    }

    // Вставка лица в постер
    private function createImage(&$image, $poster, $width, $angle, $positionX, $positionY, $gamma, $r, $g, $b, $bw){

        // Ресайзим с сохранением пропорций
        $height = imagesy($image) * ((int)$width/imagesx($image));
        $imageTmp = imagecreatetruecolor((int)$width, $height);
        imagecopyresampled($imageTmp, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));

        // Поворачиваем
        $image = imagerotate($imageTmp, (int)$angle, hexdec('FFFFFF'));

        // Гамма коррекция
        imagegammacorrect($image, 1.0, $gamma);

        // Цвето заливка
        $this->image_colorize($image,[$r,$g,$b], $bw);

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

    private function image_colorize(&$img,$rgb, $bw) {
        imageTrueColorToPalette($img,true,256);
        $numColors = imageColorsTotal($img);

        for ($x = 0; $x < $numColors; $x++) {
            list($r,$g,$b) = array_values(imageColorsForIndex($img,$x));

            if ($bw){
                // Чернобелое заливаем цветом
                $grayscale = ($r + $g + $b) / 3 / 0xff;
                imageColorSet($img,$x,
                    $grayscale * $rgb[0],
                    $grayscale * $rgb[1],
                    $grayscale * $rgb[2]
                );
            } else {
                // Цветное добавляем цвета
                imageColorSet($img,$x,
                    ($r + $rgb[0])/2,
                    ($g + $rgb[1])/2,
                    ($b + $rgb[2])/2
                );
            }


        }

        return true;
    }

    // Крутилка постеров
    public function getPoster($lastId){
        // Если есть файл с кэшем масок и их параметров
        $cacheFile = $this->config['posters'].DIRECTORY_SEPARATOR.'posters.json';
        if (is_readable($cacheFile)){
            // ...тогда берем его
            $posters = json_decode(file_get_contents($cacheFile), true);
        } else {
            // ...нет, значит создадим его
            $posters = [];
            $id = 1;
            $files = glob($this->config['posters'].DIRECTORY_SEPARATOR.'*jpg');
            asort($files);
            foreach($files as $file){
                $params = explode('_', $file);
                if(count($params) == 2){
                    $posters[$id] = [
                        'src' => $file,
                        'time' => (int)$params[1],
                    ];
                    $id++;
                }
            };
            file_put_contents($cacheFile, json_encode($posters));
        }

        ++$lastId;
        if(!isset($posters[$lastId])){
            $lastId = 1;
        }
        $result['id'] = $lastId;
        $result['src'] = $posters[$lastId]['src'];
        $result['time'] = $posters[$lastId]['time'];

        return $result;
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

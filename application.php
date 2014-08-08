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


        return true;
    }

    public function flashFace(){

        $formId = $this->getRandom(5);

        $fotoName = $this->getRandom(10);

        $fotoFolder = 'foto';

        $this->getImage($fotoFolder.DIRECTORY_SEPARATOR.$fotoName.'.jpg');

        // ToDo запись в базу
        // А пока просто тест

        $image = imagecreatefromjpeg($fotoFolder.DIRECTORY_SEPARATOR.$fotoName.'.jpg');

        $pokemon = $this->mergeImage(
            $image,
            imagecreatefrompng('/home/aagafonov/PhpstormProjects/test/public/masks/morda.png'),
            0,
            0
        );
        imagejpeg($pokemon, $fotoFolder.DIRECTORY_SEPARATOR.$fotoName.'_glass.jpg');

        // Соотношение сторон вырезанного прямоугольника 0.715
        $image = $this->cropImage($image,255, 25, 775, 753);
        imagejpeg($image, $fotoFolder.DIRECTORY_SEPARATOR.$fotoName.'_crop.jpg');

        //-----------------------------------------------------------------------------------------
        //$resize = $this->resizeImage($image, 112, 160);
        //imagejpeg($resize, $fotoFolder.DIRECTORY_SEPARATOR.$fotoName.'_resize.jpg');

        //$this->imageRoll($image, $fotoFolder.DIRECTORY_SEPARATOR.$fotoName.'_bun.gif');

        //$image = $this->rotateImage($image,0);
        //imagejpeg($image, $fotoFolder.DIRECTORY_SEPARATOR.$fotoName.'_rotate.jpg');

        //-----------------------------------------------------------------------------------------
        // merge for pokemon 1
        $pokemon = $this->mergeImage(
            $this->resizeImage($image, 298, 416),
            imagecreatefrompng('/home/aagafonov/PhpstormProjects/test/public/masks/pokemon_.png'),
            450,
            100
        );
        imagejpeg($pokemon, $fotoFolder.DIRECTORY_SEPARATOR.$fotoName.'_pokemon1.jpg');

        // merge for pokemon 2
        $pokemon = $this->mergeImage(
            $this->resizeImage($image, 186, 300),
            imagecreatefrompng('/home/aagafonov/PhpstormProjects/test/public/masks/youloveit.png'),
            570,
            480
        );
        imagejpeg($pokemon, $fotoFolder.DIRECTORY_SEPARATOR.$fotoName.'_pokemon2.jpg');

        return $formId;
    }

    public function isFace(){
        $this->getImage($this->config['tmp_file']);
        if ($face = $this->faceDetect($this->config['tmp_file'])){
            // Если морда достаточно высока и широка, она нам подходит
            if($face['w'] > $this->config['faceWidth']/100 and $face['h'] > $this->config['faceHeight']/100){
                return true;
            } else {
                return false;
            }
        }
    }

    public function getRandom($size){
        $chars="qazxswedcvfrtgbnhyujmkiolp1234567890QAZXSWEDCVFRTGBNHYUJMKIOLP";
        $lenght=StrLen($chars)-1;
        $result=null;
        while($size--)
            $result.=$chars[rand(0,$lenght)];

        return $result;
    }

    private function getImage($file){
        $data = $_POST['imgBase64'];
        $uri =  substr($data,strpos($data,",")+1);
        file_put_contents($file, base64_decode($uri));
    }

    private function faceDetect($file){
        $face = exec('./bin/find-face --cascade=./cascade/haarcascade_frontalface_default.xml '.$file);
        // Если OpenCV отдал нам 4 значения, значит он выполнился удачно
        if(count($faceCoor = explode(' ', $face)) == 4){
            $result['x'] = $faceCoor[0];
            $result['y'] = $faceCoor[1];
            $result['h'] = $faceCoor[2];
            $result['w'] = $faceCoor[3];
            return $result;
        } else {
            return false;
        }
    }


// Хелперы для работы с изображениями

    private function cropImage($image, $fromX, $fromY, $toX, $toY){
        $width = $toX - $fromX;
        $height = $toY - $fromY;
        $imageCroped = imagecreatetruecolor($width, $height);
        imagecopyresampled($imageCroped, $image, 0, 0, $fromX, $fromY, $width, $height, $width, $height);
        return $imageCroped;
    }

    private function resizeImage($image, $width, $height){
        $imageResized = imagecreatetruecolor($width, $height);
        imagecopyresampled($imageResized, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));
        return $imageResized;
    }

    private function rotateImage($image, $angle){
        return imagerotate($image, $angle, hexdec('FFFFFF'));
    }

    private function mergeImage($imageBottom, $imageTop, $xBottom=0, $yBottom=0, $xTop=0, $yTop=0){
        $imageMerge = imagecreatetruecolor(imagesx($imageTop), imagesy($imageTop));

        // Сохраняем альфа-канал.
        imagesavealpha($imageTop, true);

        // Накладываем второе изображение на первое с нужным смещением
        imagecopy($imageMerge, $imageBottom, $xBottom, $yBottom, 0, 0, imagesx($imageBottom), imagesy($imageBottom));
        imagecopy($imageMerge, $imageTop, $xTop, $yTop, 0, 0, imagesx($imageTop), imagesy($imageTop));

        return $imageMerge;

    }

/* Колобок для поиграться
     private function imageRoll($image, $file){
        $anim = new GifCreator\AnimGif();
        $frames[0] = $image;
        $durations = [];
        $i = 1;
        for($a=360; $a>0; $a=$a-20){
            $tmp = $this->rotateImage($image, $a);
            $frames[$i] = $tmp;
            $durations[$i++] = 1/1000;
        }

        $anim->create($frames, $durations);

        $anim->save($file);
    }
*/
}

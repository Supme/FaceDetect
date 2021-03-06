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

$config = [

    // Ширина и высота поля для захвата
    'width' => 640,
    'height' => 480,

    // Процент ширины и высоты морды в поле, означающий достаточное ее приближение к экрану
    'faceWidth' => 20,
    'faceHeight' => 20,

    // На сколько увеличить гамму (коррекция освещения)
    'gamma' => 0.2,

    // Время до вспышки
    'flashWait' => 5,

    // Сколько времени держать на экране форму если морда пропала из поля зрения
    'formWait' => 10,

    // Где будут храниться фотографии
    'photo' => 'photo',

    // Где хранятся маски
    'masks' => 'masks',

    // Где лежат постеры
    'posters' => 'posters',

    // База данных
    'dbHost' => 'localhost',
    'dbName' => 'photosalun',
    'dbUser' => 'photosalun',
    'dbPass' => 'uRSY9v5jmCas4H4L',

    // Почта
    'mailHost' => 'kladr.biz',
    'mailPort' => 587,
    'mailSMTPAuth' => true,
    'mailUsername' => 'test@dmbasis.email',
    'mailPassword' => 'passw0rd1',
    'mailSMTPSecure' => 'tls',
    'mailFrom' => 'test@dmbasis.email',
    'mailFromName' => 'Picachu',
];
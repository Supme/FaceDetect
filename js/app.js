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

// Конфигурационные переменные
var flashWait = 6; // секунды до снимка
var formWait = 60; // секунды до таймаута заполнения формы, если морда исчезла
var regardsWait = 10;

// рабочие переменные, что бы не запутаться- все глобальные
var $action; // Текущее состояние (действие)
var $flashTimer; // текущее значение времени до снимка
var $formTimer; // текущее время от ушедшей из поля зрения морды в состоянии формы
var $regardsTimer; // текущее время в состоянии "спасибо за анкету"
var $flash; // bool снимок сделан
var $isFace; // bool присутствие в поле зрения морды
var $formId; // Id заполняемой формы
var $formAddress; // где потом можно заполнить форму будет?
var $posterTimer;
var $posterId;

// глобальные изображение и поток
var canvas;
var video;
var context;
var videoStreamUrl;

$(document).ready(function () {
    $action = 'init';
});

window.setInterval(function(){

    switch ($action){
        case 'init':
            $flashTimer = flashWait;
            $formTimer = formWait;
            $regardsTimer = regardsWait;
            $flash = false;
            $isFace = false;
            $posterId = 0;

            canvas = document.getElementById('canvas');
            video = document.getElementById('video');
            context = canvas.getContext('2d');
            videoStreamUrl = false;
            // navigator.getUserMedia  и   window.URL.createObjectURL (смутные времена браузерных противоречий 2012)
            navigator.getUserMedia = navigator.getUserMedia || navigator.webkitGetUserMedia || navigator.mozGetUserMedia || navigator.msGetUserMedia;
            window.URL.createObjectURL = window.URL.createObjectURL || window.URL.webkitCreateObjectURL || window.URL.mozCreateObjectURL || window.URL.msCreateObjectURL;

            // запрашиваем разрешение на доступ к поточному видео камеры
            navigator.getUserMedia({video: true}, function (stream) {
                // разрешение от пользователя получено
                // скрываем подсказку
                //allow.style.display = "none";
                // получаем url поточного видео
                videoStreamUrl = window.URL.createObjectURL(stream);
                // устанавливаем как источник для video
                video.src = videoStreamUrl;

                $action = 'poster';
            }, function () {
                console.log('Что-то не так с видео потоком или пользователь запретил его использовать');
            });

            break;

        case 'poster':
            if($isFace){
                $action = 'flash';
            } else {
                $flashTimer = flashWait;
                showMessage('info', '<strong>Подойди ко мне поближе!</strong>');
                showPoster(true);
                if($posterTimer>0){
                    --$posterTimer;
                } else {
                    $.ajax({
                        type: "POST",
                        url: "../index.php",
                        data: {
                            action: 'getPoster',
                            id: $posterId
                        },
                        success:function( msg ) {
                            $posterId = msg.id;
                            slidePoster(msg.src);
                            $posterTimer = msg.time;
                            console.info('id: ' + msg.id + '\nposter: ' + msg.src + '\ntime: ' + msg.time);
                        }
                    });
                }
            }

            break;

        case 'flash':
            if(!$isFace){
                $action = 'poster';
            } else {
                showPoster(false);
                if($flashTimer == 1){
                    showBird(true);
                }
                if($flashTimer>0){
                    showMessage('success', '<strong>Внимание, через ' + $flashTimer + ' сек вылетит птичка</strong>');
                    --$flashTimer;
                } else {
                    //ToDo надо бы сделать тут фотовспышку, в виде блинка белым фоном с птичками.
                    showMessage('warning', '<strong>Птичка полетела!!!</strong>');
                    flashFace();
                    showBird(false);
                    $flash = true;
                    $action = 'form';
                }
            }

            break;

        case 'form':
            if($flash){
                showMessage('primary', '<strong>Заполни форму!</strong>');
                showForm(true);
                $flash = false;
            } else {
                if(!$isFace){
                    if($formTimer>0){
                        --$formTimer;
                    } else {
                        showForm(false);
                        $action = 'poster'
                    }
                } else {
                    $formTimer = formWait;
                }
            }

            break;

        case 'regards':
            if($regardsTimer>0){
                --$regardsTimer;
            } else {
                showMessage('danger', '<strong>Следующий!</strong>');
                $regardsTimer = regardsWait;
                $action = 'init';
            }

            break;

        default:
            console.log('Документ не готов. Инициализация.')
    }

    if (videoStreamUrl){
        context.drawImage(video, 0, 0, video.width, video.height);
        var base64dataUrl = canvas.toDataURL('image/jpeg', 0.1); // цифры это качество сжатия jpeg
        $.ajax({
            type: "POST",
            url: "../index.php",
            data: {
                action: 'detectFace',
                imgBase64: base64dataUrl
            },
            success:function( msg ) {
                if(msg.isFace){
                    $isFace = true;
                } else {
                    $isFace = false;
                }
            }
        });
    }

},1000);

// Функции и действия

function slidePoster(src){
    var img = $('#poster');
    img.removeClass();
    img.addClass('magictime holeOut');
    setTimeout(function(){
        img.attr('src', '../' + src);
        img.removeClass();
        img.addClass('magictime swap');
    }, 1000);
}

function sendForm(){
    console.info('SendForm');
    $('#poster').hide();
    $.ajax({
        type: "POST",
        url: "../index.php",
        data: {
            action: 'sendForm',
            fname: $('#fname').val(),
            mname: $('#mname').val(),
            lname: $('#lname').val(),
            gender: $('#gender').val(),
            email: $('#email').val(),
            formId: $('#formId').val()
        },
        success:function( msg ) {
            console.info(msg.status);
            showForm(false);
            showMessage('warning', '<strong>Спасибо! Фото отправленно на почтовый ящик! Готовится следующий...</strong>');
            $action = 'regards';
        }
    })
}

function flashFace(){
    context.drawImage(video, 0, 0, video.width, video.height);
    var base64dataUrl = canvas.toDataURL('image/jpeg');
    $.ajax({
        type: "POST",
        url: "../index.php",
        data: {
            action: 'flashFace',
            imgBase64: base64dataUrl
        },
        success:function( msg ) {
            $formId = msg.formId;
            $formAddress = msg.url;
            $('#formId').val($formId);
            $('#qrcode').html(
                '<img src="../qr.php?text=' + $formAddress + '?' + $formId + '"/><br/>' +
                'Анкету можно заполнить позже по адресу: ' + $formAddress + '<br/>' +
                'Id Вашей анкеты: ' + $formId
            );
            console.info($formId);
        }
    })
    Sound.play('fotospusk');
}

// показать/спрятать форму и ее контент
function showForm($show){
    if($show){

        $('#form-modal').modal('show');

    } else {

        $('#form-modal').modal('hide');
        clearForm();

    }
}

// Очистка формы
function clearForm(){
    VKI_close();
    $('#qrcode').html('');
    $('input').removeAttr('checked').val('');
    $('label.error').remove();
}

// отображение сообщений
function showMessage(id, message){
    alert = $('.alert');
    if(alert.hasClass('alert-primary')) alert.removeClass('alert-primary');
    if(alert.hasClass('alert-info')) alert.removeClass('alert-info');
    if(alert.hasClass('alert-success')) alert.removeClass('alert-success');
    if(alert.hasClass('alert-warning')) alert.removeClass('alert-warning');
    if(alert.hasClass('alert-danger')) alert.removeClass('alert-danger');
    alert.addClass('alert-' + id);
    alert.html(message);


}

// показ постера
function showPoster(show){
    if(show){
        $('#poster').show();
        $('#bird').hide();
        $('#face').hide();
    } else {
        $('#poster').hide();
        $('#bird').hide();
        $('#face').show();
    }
}

// показ птички
function showBird(show) {
    if (show) {
        console.log('Птичка!!!');
        $('#poster').hide();
        $('#bird').show();
        $('#face').hide();
    } else {
        $('#poster').hide();
        $('#bird').hide();
        $('#face').show();
        console.log('Улетела птичка...');
    }
}
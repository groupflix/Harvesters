 <?php
    // Set the content-type
    header('Content-type: image/jpeg');

    /* Attempt to open */
        $im = @imagecreatefromjpeg('http://thetvdb.com/banners/graphical/129261-g14.jpg');

        /* See if it failed */
        if(!$im)
        {
            // Create some colors
        $white = imagecolorallocate($im, 255, 255, 255);
        $grey = imagecolorallocate($im, 128, 128, 128);
        $black = imagecolorallocate($im, 115, 150, 195);
        imagefilledrectangle($im, 0, 0, 758, 280, $white);

        // The text to draw
        $text = 'My Name';
        // Replace path by your own font path
        $font = 'http://dev.myubi.tv/v3/fonts/proximanova-bold-webfont.ttf';

        // Add some shadow to the text
        imagettftext($im, 20, 0, 11, 21, $grey, $font, $text);

        // Add the text
        imagettftext($im, 20, 0, 10, 20, $black, $font, $text);

        // Using imagepng() results in clearer text compared with imagejpeg()
        imagejpeg($im);
        //imagedestroy($im);


        }else
    {
         //you want to do something here if your image didn't open like maybe fpassthru an alternative image
    }
?>
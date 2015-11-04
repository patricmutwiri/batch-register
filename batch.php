<?php
/**
 * @version   $Id: application.php 15097 2010-02-27 14:19:54Z ian $
 * @package   Joomla
 * @subpackage  batchupload
 * @copyright Copyright (C) 2005 - 2010 Open Source Matters. All rights reserved.
 * @license   GNU/GPL, see LICENSE.php
 * Joomla! is free software. This version may have been modified pursuant to the
 * GNU General Public License, and as distributed it includes or is derivative
 * of works licensed under the GNU General Public License or other free or open
 * source software licenses. See COPYRIGHT.php for copyright notices and
 * details.
 */
// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

$user =& JFactory::getUser();
$userr 	= clone(JFactory::getUser());
$appx = JFactory::getApplication();
$app = $appx;
jimport('joomla.user.helper');
$pathway 	      = & $mainframe->getPathway();
$config	      = & JFactory::getConfig();
$authorize	      = & JFactory::getACL();
$document       = & JFactory::getDocument();
$usersConfig = &JComponentHelper::getParams( 'com_users' );
$newUsertype = $usersConfig->get( 'new_usertype' );
$usersConfig = &JComponentHelper::getParams( 'com_users' );
if ($usersConfig->get('allowUserRegistration') == '0')
{
    JError::raiseError( 403, JText::_( 'Access Forbidden' ));
    return;
}
if (!$newUsertype)
{
    $newUsertype = 'Registered';
}
if(!$user->id){
    $appx->enqueueMessage(' Please Login with rights first' );
?>
<div>
<hr/>
<p> STAT: Not Logged In </p>
</div>
  <script>
    alert( ' You have to be Logged in first ' );
  </script>  
<?php } else {
$isroot = $user->designation;
if($isroot == 'hr assistant') { //admin
    $db         = JFactory::getDBO();
    $config     = JFactory::getConfig();
	$dbprefix   = $config->getValue('dbprefix'); 
    include "import.php";	    
	$csv = new Quick_CSV_import();
	$mysqli = $csv->mysqli();
	if ($mysqli->connect_error) {
		die('Connect Error (' . $mysqli->connect_errno . ') '
				. $mysqli->connect_error);
	} else {
		//echo 'Success... ' . $mysqli->host_info . "\n";
	}
    $file_source = isset($file_source) ? $file_source : '';

    $_POST["field_separate_char"] = isset($_POST["field_separate_char"]) ? $_POST["field_separate_char"] : ",";
    $_POST["field_enclose_char"]  = isset($_POST["field_enclose_char"]) ? $_POST["field_enclose_char"] : "\"";
    $_POST["field_escape_char"]   = isset($_POST["field_escape_char"]) ? $_POST["field_escape_char"] : "\\";
    $arr_encodings = $csv->get_encodings(); //take possible encodings list
    $arr_encodings["default"] = "[default database encoding]"; //set a default (when the default database encoding should be used)

    if(!isset($_POST["encoding"]))
      $_POST["encoding"] = "default"; //set default encoding for the first page show (no POST vars)
    if(isset($_POST["Go"]) && ""!=$_POST["Go"]) //form was submitted
    {
      $csv->file_name = $_FILES['file_source']['tmp_name'];
      //optional parameters
      $csv->use_csv_header      = isset($_POST["use_csv_header"]);
      $csv->field_separate_char = $_POST["field_separate_char"][0];
      $csv->field_enclose_char  = $_POST["field_enclose_char"][0];
      $csv->field_escape_char   = $_POST["field_escape_char"][0];
      $csv->encoding            = $_POST["encoding"];
	  $csv->mysqli 				= $mysqli;
	  $csv->table_name			= $dbprefix.'users_dump';
      //start import now
     $csv->import();
      if(empty($csv->error)){
        echo ' <br/><p>Upload Successful</p><br/> ';
        //set passwords
        $toupdate = 'SELECT * FROM #__users_dump WHERE id > 0 and password = ""'; //newly added
        $db->setQuery($toupdate);
        $allnew = $db->loadObjectList();
        $count = count($allnew);
        if(!empty($count)) {
            for($i = 0; $i < $count; $i++){
                $pass = '1234'; //default
                $data = array(
                      "name"=>$allnew[$i]->name,
                      "username"=>$allnew[$i]->username,
                      "payroll"=>$allnew[$i]->payroll,
                      "password"=>$pass,
                      "password2"=>$pass,
                      "email"=>$allnew[$i]->email,
                      "block"=>0,
                      "branch"=>$allnew[$i]->branch,
                      "designation"=>$allnew[$i]->designation,
                      "department"=>$allnew[$i]->department,
                      "telephone"=>$allnew[$i]->telephone,
                      "level"=>$allnew[$i]->level,
                      "leavedays"=>$allnew[$i]->leavedays,
                      "gid"=>18,
                      "usertype"=>$newUsertype
                );
                $userr = new JUser;
                //Write to database
                if(!$userr->bind($data)) {
                    throw new Exception("Could not bind data. Error: " . $userr->getError());
                }
                $userr->set('id', 0);
                $userr->set('usertype', $newUsertype);
                $userr->set('gid', $authorize->get_group_id( '', $newUsertype, 'ARO' ));
                $date =& JFactory::getDate();
                $userr->set('registerDate', $date->toMySQL());
                $useractivation = $usersConfig->get( 'useractivation' );
                if ($useractivation == '1')
                    {
                        jimport('joomla.user.helper');
                        $userr->set('activation',$pass );
                        $userr->set('block', '1');
                    }
                if($userr->save()) {
                    $del23 = 'DELETE FROM #__users_dump WHERE password = ""';
                    $db->setQuery($del23);
                    $db->query();
                }
            }
        }
      } else {
        echo ' An Error Occured During Upload. ';
      }
    }
    else
      $_POST["use_csv_header"] = 1;
     ?>
    <!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
    <html>
    <head>
        <title>Quick User import</title>
    </head>
    <body bgcolor="#f2f2f2">
      <h2 class="uploadH2" align="center">Quick Users' import</h2>
      <form method="post" action="<?php JURI::current() ?>" enctype="multipart/form-data">
        <table border="0" align="center" class="user_import">
          <tr>
            <td>Source CSV file to import:</td>
            <td rowspan="30" width="10px">&nbsp;</td>
            <td><input type="file" name="file_source" id="file_source" class="edt" value="<?php echo $file_source  ?>"></td>
          </tr>
          <tr>
            <td>Use CSV header:</td>
            <td><input type="checkbox" name="use_csv_header" id="use_csv_header" <?php echo (isset($_POST["use_csv_header"])?"checked":"") ?>/></td>
          </tr>
          <tr>
            <td>Separate char:</td>
            <td><input type="text" name="field_separate_char" id="field_separate_char" class="edt_30"  maxlength="1" value="<?php echo (""!=$_POST["field_separate_char"] ? htmlspecialchars($_POST["field_separate_char"]) : ",") ?>"/></td>
          </tr>
          <tr>
            <td>Enclose char:</td>
            <td><input type="text" name="field_enclose_char" id="field_enclose_char" class="edt_30"  maxlength="1" value="<?php echo (""!=$_POST["field_enclose_char"] ? htmlspecialchars($_POST["field_enclose_char"]) : htmlspecialchars("\"")) ?>"/></td>
          </tr>
          <tr>
            <td>Escape char:</td>
            <td><input type="text" name="field_escape_char" id="field_escape_char" class="edt_30"  maxlength="1" value="<?php echo (""!=$_POST["field_escape_char"] ? htmlspecialchars($_POST["field_escape_char"]) : "\\") ?>"/></td>
          </tr>
          <tr>
            <td>Encoding:</td>
            <td>
              <select name="encoding" id="encoding" class="edt">
              <?php
                if(!empty($arr_encodings))
                  foreach($arr_encodings as $charset=>$description):
               ?>
                <option value="<?php echo $charset ?>"<?php echo ($charset == $_POST["encoding"] ? "selected=\"selected\"" : "") ?>>
					<?php echo $description ?>
				</option>
              <?php endforeach; ?>
              </select>
            </td>
          </tr>
          <tr>
            <td colspan="3">&nbsp;</td>
          </tr>
          <tr>
            <td colspan="3" align="center">
				<input type="Submit" class="uploadusers" name="Go" value="Upload Users" onclick=" var s = document.getElementById('file_source'); if(null != s && '' == s.value) {alert('Can not find a file'); s.focus(); return false;}">
			</td>
          </tr>
        </table>
      </form>
    <?php echo (!empty($csv->error) ? "<hr/>Errors: ".$csv->error : ""); ?>

    </body>
    </html>
    <?php
    } else {
        $appx->enqueueMessage(' Please Login with upload rights first' );
        echo '<h4> Please Login as <em>hr assistant</em> first';
    }
    }
   ?>

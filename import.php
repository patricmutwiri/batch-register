<?php
/*
 * handle the imports
 * users only
 */

class Quick_CSV_import
{
  var $table_name; //where to import to
  var $file_name;  //where to import from
  var $use_csv_header; //use first line of file OR generated columns names
  var $field_separate_char; //character to separate fields
  var $field_enclose_char; //character to enclose fields, which contain separator char into content
  var $field_escape_char;  //char to escape special symbols
  var $error; //error message
  var $arr_csv_columns; //array of columns
  var $table_exists; //flag: does table for import exist
  var $encoding; //encoding table, used to parse the incoming file. Added in 1.5 version
  
  function Quick_CSV_import($file_name="")
  {
	  
    $this->file_name = $file_name;
    $this->arr_csv_columns = array();
    $this->use_csv_header = true;
    $this->field_separate_char = ",";
    $this->field_enclose_char  = "\"";
    $this->field_escape_char   = "\\";
    $this->table_exists = false;
  }
  function mysqli(){
	$config     = JFactory::getConfig();
    $host       = $config->getValue('host');
    $username   = $config->getValue('user');
    $pass       = $config->getValue('password');
    $databasex  = $config->getValue('db');
    $subjectx   = $config->getValue('sitename');
    $dbprefix   = $config->getValue('dbprefix');
	$mysqli = new mysqli($host, $username, $pass, $databasex);
	return $mysqli;
  }
  
  function import()
  {
	  $mysqli = $this->mysqli();
    if($this->table_name=="")
      $this->table_name = "temp_".date("d_m_Y_H_i_s");
    
    $this->table_exists = false;
    $this->create_import_table();
    
    if(empty($this->arr_csv_columns))
      $this->get_csv_header_fields();

    if("" != $this->encoding && "default" != $this->encoding)
      $this->set_encoding();
	  
    if($this->table_exists)
    {
      $sql = "LOAD DATA INFILE '".$mysqli->real_escape_string($this->file_name).
             "' INTO TABLE `".$this->table_name.
             "` FIELDS TERMINATED BY '".$mysqli->real_escape_string($this->field_separate_char).
             "' OPTIONALLY ENCLOSED BY '".$mysqli->real_escape_string($this->field_enclose_char).
             "' ESCAPED BY '".$mysqli->real_escape_string($this->field_escape_char).
             "' ".
             ($this->use_csv_header ? " IGNORE 1 LINES " : "")
             ."(`".implode("`,`", $this->arr_csv_columns)."`)";
      $res = $mysqli->query($sql);
      $this->error = $mysqli->error;
    }
  }
  
  //returns array of CSV file columns
  function get_csv_header_fields()
  {
	$mysqli = $this->mysqli();
    $this->arr_csv_columns = array();
    $fpointer = fopen($this->file_name, "r");
	$e_rr = array();
    if ($fpointer) {
      $arr = fgetcsv($fpointer, 10*1024, $this->field_separate_char);
      if(is_array($arr) && !empty($arr))
      {		
        if($this->use_csv_header)
        {
		  $missingFs = '';
		  if(!in_array('username',$arr)) { 
			$e_rr[] = '* username missing';
	      }
		  if(!in_array('name',$arr)) { 
			$e_rr[] = '* name missing';
	      }
		  if(!in_array('payroll',$arr)) { 
			$e_rr[] = '* payroll missing';
	      }
		  if(!in_array('email',$arr)) { 
			$e_rr[] = 'email missing';
	      }
		  if(!in_array('branch',$arr)) { 
			$e_rr[] = '* branch missing';
	      }
		  if(!in_array('department',$arr)) { 
			$e_rr[] = '* department missing';
	      }
		  if(!in_array('designation',$arr)) { 
			$e_rr[] = '* designation missing';
	      }
		  if(!in_array('telephone',$arr)) { 
			$e_rr[] = '* telephone missing';
	      }
          if(!in_array('leavedays',$arr)) {
			$e_rr[] = '* leavedays missing';
	      }
		  if(!in_array('level',$arr)) { 
			$e_rr[] = '* level missing';
	      }
		  $missingFs = implode('*',$e_rr);
		  $ercount = count($e_rr);
		  if(!empty($ercount))
			//$js =  '<script type="text/javascript"> var pr = confirm("Users Not Uploaded. See below : '.$missingFs.', Click ok to try again. ");alert(pr);if(pr){window.location = window.location.href;} else { window.location = "index.php"; }</script>';
            exit('<script type="text/javascript"> var pr = confirm("Users Not Uploaded. See below : '.$missingFs.', Click ok to try again. ");alert(pr);if(pr){window.location = window.location.href;} else { window.location = "index.php"; }</script>');
			
          foreach($arr as $val)
            if(trim($val)!="")
              $this->arr_csv_columns[] = $val;
			  
        }
        else
        {
          $i = 1;
          foreach($arr as $val)
            if(trim($val)!="")
              $this->arr_csv_columns[] = "column".$i++;
        }
      }
      unset($arr);
      fclose($fpointer);
    }
    else
      $this->error = "file cannot be opened: ".(""==$this->file_name ? "[empty]" : $mysqli->real_escape_string($this->file_name));
    return $this->arr_csv_columns;
  }
  
  function create_import_table()
  {
	  $mysqli = $this->mysqli();
    $sql = "CREATE TABLE IF NOT EXISTS ".$this->table_name." (";
    
    if(empty($this->arr_csv_columns))
      $this->get_csv_header_fields();
    
    if(!empty($this->arr_csv_columns))
    {
      $arr = array();
	  
      for($i=0; $i<sizeof($this->arr_csv_columns); $i++)
          $arr[] = "`".$this->arr_csv_columns[$i]."` TEXT";
      		$sql .= implode(",", $arr);
      		$sql .= ")";
      		$res = $mysqli->query($sql);
      	$this->error = $mysqli->error;
      $this->table_exists = ""== $mysqli->error;
    }
  }
  //returns recordset with all encoding tables names, supported by your database
  function get_encodings()
  {
	$mysqli = $this->mysqli();
    $rez = array();
    $sql = "SHOW CHARACTER SET";
    $res = $mysqli->query($sql);
    if($res->num_rows > 0)
    {
      while ($row = $res->fetch_assoc() )
      {
        $rez[$row["Charset"]] = ("" != $row["Description"] ? $row["Description"] : $row["Charset"]); 
		//some MySQL databases return empty Description field
      }
    }
    return $rez;
  }
  
  //defines the encoding of the server to parse to file
  function set_encoding()
  {
  $mysqli = $this->mysqli();
  $encoding = $this->encoding;
    if("" == $encoding)
		$res = $mysqli->set_charset($encoding);
    return $mysqli->error;
  }
}

?>

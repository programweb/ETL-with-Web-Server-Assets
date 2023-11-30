<?php

namespace ihmeuw_disease_injury;

class parseInput {

  public function parseJSON($filepath){

    // read and parse file
    $jsonData = file_get_contents($filepath);
    if( ! $jsonData ){
      drupal_set_message('File read error.');
      return array();
    }

    $decodedJSON = json_decode($jsonData, true);

    $json_error = json_last_error();
    if($json_error == JSON_ERROR_NONE) {
      return $decodedJSON;
    }

    $json_error_message = json_last_error_msg();  // error string of the last json_decode() call (could be "No error")
    if( $json_error_message ){ // false if error with error msg
      drupal_set_message($json_error_message, $type = 'error');
    }
    return array();
  }

  /**
   * @param $filepath
   *
   * @return array
   */
  public function parseCSV($filepath, $delimiter = ','){

    // initialize array to save our data
    $data_array = array();
    // Set line ending detection to support CSV files with Windows, Mac line endings
    ini_set('auto_detect_line_endings', TRUE);
    // open file in read mode
    $file_handle = fopen($filepath, 'r');
    // if we successfully opened the file
    if ($file_handle !== FALSE) {
      $keys = array();
      // keep track of row index (zero-based)
      $i = 0;
      while( ($lineArray = fgetcsv($file_handle, 4000, $delimiter, '"')) !== FALSE ){
        for($j = 0; $j < count($lineArray); $j++){
          if($i == 0){
            $keys[$j] =  $lineArray[$j];
          }
          else {
            $data_array[$i - 1][ $keys[$j] ] = $lineArray[$j];
          }
        }
        $i++;
      }
      fclose($file_handle);
    }
    else { // we could not open our file
      $msg = 'Unable to read file at ' . $filepath;
      drupal_set_message($msg, $type = 'error');
    }
    // Reset line ending value after closing file
    ini_set('auto_detect_line_endings', FALSE);

    // This should be an array of multiple pages' data to save
    return $data_array;
  }
}
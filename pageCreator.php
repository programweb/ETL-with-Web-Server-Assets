<?php

namespace ihmeuw_disease_injury;

class pageCreator {

  /**
   * @param $input_array
   *
   * @return array
   */
  public function save_pages(&$input_array, &$form_state, $form, $node_round, $node_published){
    $messages = array();
    $count = 0;
    foreach ($input_array as $page_array){
      $pg_info_array = $this->handle_single_page($page_array, $form_state, $form, $node_round, $node_published);

      if ($pg_info_array['success'] === TRUE){
        if(isset($pg_info_array['nid']) && $pg_info_array['nid']){
          $url = ($pg_info_array['alias'] ?: '/' . $pg_info_array['uri'] );
          $messages['newpages'][] = ($pg_info_array['is_new'] ? 'Made new ' : 'Updated existing ') .
            "page at: <a href='{$url}' target='_blank'>{$url}</a>" .
            (isset($pg_info_array['title']) && $pg_info_array['title'] ? " &mdash; {$pg_info_array['title']}" : '');
        }
        $count++;
      } else {
        $title = (isset($pg_info_array['title']) ? $pg_info_array['title'] : 'no_title');
        $messages['failure'][] = "Failed to save page for: $title.";
      }
      if( $pg_info_array['errMsg'] ){
        $messages['failure'][] = $pg_info_array['errMsg'];
      }
    }
    if( $count > 0 ){
      $messages['success'] = "Saved {$count} " . ($count == 1 ? 'node' : 'nodes') . ' successfully.';
    }
    else{
      array_unshift($messages['failure'], 'No pages were saved.');
    }
    return array($count, $messages);
  }

  /**
   * @param $page_array
   *
   * @return array
   */
  public function handle_single_page(&$page_array, &$form_state, $form, $node_round, $node_published){
    global $user;

    $errMsg = $this->validate_pgarr($page_array);
    $node_round = (int)$node_round;
    if( $node_round < 2019 ){
      $errMsg .= ' Round should be a 4 digit year equal to or greater than 2019';
    }

    if( $errMsg ){
      return array(
        'is_new' => 0,
        'nid' => 0,
        'title' => (isset($page_array['topic_name']) && $page_array['topic_name'] ? $page_array['topic_name'] : ''),
        'alias' => '',
        'uri' => '',
        'success' => FALSE,
        'errMsg' => 'Error in supplied data. ' . $errMsg
      );
    }

    /**
     * Prepare Node
     */
    $nid = $this->fetch_nid_from_pgarr($page_array, $node_round);

    $node = ( $nid ? node_load($nid) : (object)[] );
    $node->type = 'disease_and_injury';
    node_object_prepare($node); // Sets some defaults. Invokes hook_prepare() and hook_node_prepare().
    $node->language = LANGUAGE_NONE; // Or e.g. 'en' if locale is enabled
    $node->uid = $user->uid;
    $node->status = $node_published; // (1 or 0): published or not
    $node->promote = 0; //(1 or 0): promoted to front page

    /**
     * Specifics for this content type
     */
    $node->field_di_level[ $node->language ][0]['value'] = $page_array['level'];
    $node->field_di_round[ $node->language ][0]['value'] = $node_round;
    $node->field_shared_id_di[ $node->language ][0]['value'] = $page_array['topic_id'];

    switch( $page_array['topic_sub_type'] ){
      case 'di':
        $node->field_cause_or_rei[ $node->language ][0]['value'] = 'cause';
        $node->field_topic_sub_type[ $node->language ][0]['value'] = 'disease';
        $paginatorPathPart = 'cause';
        $genLabel = 'cause';
        break;
      case 'imp':
        $node->field_cause_or_rei[ $node->language ][0]['value'] = 'rei';
        $node->field_topic_sub_type[ $node->language ][0]['value'] = 'impairment';
        $paginatorPathPart = 'cause';
        $genLabel = 'impairment';
        break;
      case 'rf':
        $node->field_cause_or_rei[ $node->language ][0]['value'] = 'rei';
        $node->field_topic_sub_type[ $node->language ][0]['value'] = 'risk';
        $paginatorPathPart = 'risk';
        $genLabel = 'risk';
        break;
      default:
        $node->field_cause_or_rei[ $node->language ][0]['value'] = 'na';
        $node->field_topic_sub_type[ $node->language ][0]['value'] = 'na';
        $paginatorPathPart = 'no_path';
        $genLabel = '';
        $errMsg = "Expected sub-type not found.";
        break;
    }

    $node->title = $page_array['topic_name'] . " — Level {$page_array['level']} {$genLabel}";
    $node->path['alias'] = 'results/' . $node->type . '/gbd_' . $node_round . '/' .
      str_replace(' ', '-', strtolower( str_replace('— ', '', $node->title)));
    $node->path['pathauto'] = 0;

    if( ! $errMsg ){
      list($errMsg, $content) = $this->getContent($paginatorPathPart, $page_array['topic_id'], $node_round);
    }

    if( $errMsg ) {

      $pg_info_array = array(
        'is_new' => !(bool)$nid,
        'nid' => ($node->nid ?: 0),
        'title' => ($node->title?:''),
        'alias' => '',
        'uri' => '',
        'success' => FALSE,
        'errMsg' => $errMsg
      );

    }
    else{
      $node->field_content_di[$node->language][0]['value'] = $content;
      $node->field_content_di[$node->language][0]['format'] = 'full_html_no_added_break';

      /**
       * Save Node
       */
      $node = node_submit($node); // Prepare node for saving
      node_save($node); // Saves changes to a node or adds a new node

      // perform node validations & get error messages
      $errMsg = get_node_errMsg($node, $form, $form_state);

      if(property_exists($node, 'nid')) {
        $pg_info_array = array(
          'is_new' => !(bool)$nid,
          'nid' => $node->nid,
          'title' => $node->title,
          'alias' => url('node/' . $node->nid),
          'uri' => node_uri($node),
          'success' => TRUE,
          'errMsg' => $errMsg  // even though a node was made there may be another error
        );
      }
      else{
        $pg_info_array = array(
          'is_new' => !(bool)$nid,
          'nid' => 0,
          'title' => '',
          'alias' => '',
          'uri' => '',
          'success' => FALSE,
          'errMsg' => $errMsg
        );
      }
    }

    return $pg_info_array;
  }


  /**
   * @param $page_array
   *
   * @return string error message (empty string if no error)
   */
  public function validate_pgarr(&$page_array){

    $msg = '';
    if( ! $page_array['topic_id'] ){
      $msg = 'Zero or Missing topic_id (shared data id).';
    }
    elseif( ! $page_array['topic_name']){
      $msg = 'Missing topic_name.';
    }
    elseif( ! $page_array['topic_sub_type']){
      $page_array['sub_type'] = '';
      $msg = 'Missing topic_sub_type.';
    }
    elseif( ! in_array( $page_array['topic_sub_type'], array('di', 'imp', 'rf')) ){
      $page_array['sub_type'] = '';
      $msg = 'Unexpected topic_sub_type.';
    }

    return $msg;
  }

  /**
   * @param $page_array
   *
   * @return integer (0 if no result)
   */
  public function fetch_nid_from_pgarr(&$page_array, $node_round){

    $page_array['sub_type'] = ($page_array['topic_sub_type'] == 'di' ? 'cause' : 'rei');

    $nid = db_query("SELECT nid
                        FROM {node} n
                        JOIN {field_data_field_shared_id_di} sid ON sid.entity_id = n.nid
                        JOIN {field_data_field_cause_or_rei} cor ON cor.entity_id = n.nid
                        JOIN {field_data_field_di_round} rnd ON rnd.entity_id = n.nid
                        WHERE n.type = 'disease_and_injury'
                        AND sid.field_shared_id_di_value = :shared_id
                        AND cor.field_cause_or_rei_value = :cause_or_rei
                        AND rnd.field_di_round_value = :di_round",
                      array(':shared_id' => $page_array['topic_id'],
                            ':cause_or_rei' => $page_array['sub_type'],
                            ':di_round' => $node_round ))
                      ->fetchField();

    return (int)$nid;
  }

  public function prependPath($envelope, $filenm){
    $filenm = strtolower(trim($filenm, ' /'));
    if( $filenm == 'scatter_legend.svg' ){
      $prepend = '/' .
          variable_get('file_public_path', conf_path() . '/files') .
          '/disease_and_injury/gbd_' . $this->round . '/static/';
    }
    else{
      $prepend = $this->imgDirPath;
    }

    return $prepend . $filenm;
  }

  /**
   * @param $str string
   *
   * @return string (incoming string translated with correct image paths)
   */
  public function processStr($str){
    $patt = '/{{(.*)}}/iU';

    $str = preg_replace_callback( $patt,
                  function($m){ return $this->prependPath($m[0], $m[1]); },
                  $str);

    return $str;
  }

  public function getContent($paginatorPathPart, $topic_id = 0, $node_round = 0){
    $err = '';
    $html = '';
    $round = (int)$node_round;

    if( $round < 2019 ){
      return array('Round should be a 4 digit year equal to or greater than 2019', $html);
    }
    if( ! $topic_id){
      return array('No topic ID to fetch HTML', $html);
    }
    if( ! in_array($paginatorPathPart, array('cause', 'risk'))){
      return array("Did not get cause or risk for id: {$topic_id}", $html);
    }

    $serverPath = "/www/{$_SERVER['SERVER_NAME']}/htdocs";
    $basePath = "{$serverPath}/sites/default/files/disease_and_injury/gbd_{$round}";

    $fiPath = "{$basePath}/html/{$paginatorPathPart}/{$topic_id}/drupal.html";
    $imgDirPath = "{$basePath}/topic_static/{$paginatorPathPart}/{$topic_id}/";


    if( ! file_exists($imgDirPath) ) $err = "Image directory not found for id: {$topic_id}";
    elseif( ! is_executable($imgDirPath)) $err = "Image directory not accessible for id: {$topic_id}";
    elseif( ! file_exists($fiPath) ) $err = "HTML file not found for id: {$topic_id}";
    elseif( ! is_readable($fiPath)) $err = "HTML file not readable for id: {$topic_id}";
    else{
      $html = file_get_contents($fiPath);
      if($html) {
        $this->imgDirPath = str_replace($serverPath, '', $imgDirPath);
        $this->round = $round;
        $html = $this->processStr($html);
      }
      else{
        $html = '';
        $err = "Problem getting HTML content for id: {$topic_id}";
      }
    }

    return array($err, $html);
  }
}


/**
 * Performs validation checks on the given node.
 */
function get_node_errMsg($node, $form, &$form_state) {
  $errStr = '';
  if (isset($node->nid) && (node_last_changed($node->nid) > $node->changed)) {
    $errStr = t('The content on this page has either been modified by another ' .
      'user, or you have already submitted modifications using this form. As a result, ' .
      'the changes cannot be saved.');
  }
  elseif (!empty($node->name) && !($account = user_load_by_name($node->name))) { // Validate the "authored by"
    // The use of empty() is mandatory in the context of usernames
    // as the empty string denotes the anonymous user. In case we
    // are dealing with an anonymous user we set the user ID to 0.
    $errStr = t('The username %name does not exist.', array('%name' => $node->name));
  }
  elseif (!empty($node->date) && strtotime($node->date) === FALSE) { // Validate the "authored on" field.
    $errStr = t('A valid date was not specified.');
  }

  if($errStr){
    return 'Node ' . ($node->title ? ' - ' . $node->title . ' - ' : '') .
      'not valid because: ' . $errStr;
  }

  // THIS NEXT PORTION does not return anything but will show errors on the page.
  // Invoke hook_validate() for node type specific validation and
  // hook_node_validate() for miscellaneous validation needed by modules. Can't
  // use node_invoke() or module_invoke_all(), because $form_state must be
  // receivable by reference.
  $function = node_type_get_base($node) . '_validate';
  if (function_exists($function)) {
    $function($node, $form, $form_state);
  }
  foreach (module_implements('node_validate') as $module) {
    $function = $module . '_node_validate';
    $function($node, $form, $form_state);
  }
  return '';
}
<?php 

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die( 'Restricted access' );

jimport( 'joomla.application.component.view');

class ProjectEditorViewRepetitions extends JView{
	
  function display($tpl = null){
    $iTrialId = JRequest::getVar("id");
	
    //get the trial
    $oRepititionModel =& $this->getModel();
    $oRepititionArray = $oRepititionModel->findRepititionsByTrial($iTrialId);
    $_REQUEST[RepetitionPeer::TABLE_NAME] = serialize($oRepititionArray);
	
    parent::display($tpl);
  }//end display
  
}

?>
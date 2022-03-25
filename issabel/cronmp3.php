<?php
//Manuel Vera
//manuel.vera.desarrollo@gmail.com
//Ejecución en cron cada 5 minutos para 
date_default_timezone_set('America/Guayaquil');

include_once "/var/www/html/libs/paloSantoDB.class.php";
$arr = parse_ini_file("/etc/issabel.conf");
$cadena_dsn = "mysql://root:".$arr['mysqlrootpwd']."@localhost/asteriskcdrdb";
$oDB = new paloDB($cadena_dsn);

//El query busca las grabaciones del día que aun contengan la grabación como wav
$sQuery = "SELECT cdr.*,concat('/var/spool/asterisk/monitor/',DATE_FORMAT(calldate,'%Y'),'/',DATE_FORMAT(calldate,'%m'),'/',DATE_FORMAT(calldate,'%d'),'/',cdr.recordingfile) fullpath from cdr where calldate > date(now()) and recordingfile like '%wav' ";

$arrCdr = $oDB->fetchTable($sQuery,true);

foreach ($arrCdr as $key => $value) {
	$arrData = array("callTime"=>$value['calldate'],"audioFormat"=>"MP3",
                 "other"=>array("duration"=>$value["duration"],"Interaction_Time"=>$value["calldate"],
                                "Metadata_callerPhoneNumber"=>$value['src'],"Metadata_dialedPhoneNumber"=>$value["dst"],
                                ));
	$xml_data = new SimpleXMLElement('<?xml version="1.0"?><data></data>');
	$arrFilename = pathinfo($value['recordingfile']);
	$audiomixed = $value['recordingfile'];
	if($arrFilename['dirname']=='.'){
		$arrFilename = pathinfo($value['fullpath']);
		$audiomixed = $value['fullpath'];
	}
	if(
		//!file_exists($arrFilename["dirname"]."/".$arrFilename['filename'].".mp3") && 
		file_exists($arrFilename["dirname"]."/".$arrFilename['filename']."lega.wav") && 
		file_exists($arrFilename["dirname"]."/".$arrFilename['filename']."legb.wav")
		){

		$comando = "ffmpeg -i ".$arrFilename["dirname"]."/".$arrFilename['filename']."lega.wav -i ".$arrFilename["dirname"]."/".$arrFilename['filename']."legb.wav -filter_complex join=inputs=2:channel_layout=stereo ".$arrFilename["dirname"]."/".$arrFilename['filename'].".mp3";
		$out = exec($comando,$output);
		actualizarCDR($audiomixed,$value['uniqueid'],$oDB);
		unlink($arrFilename["dirname"]."/".$arrFilename['filename']."lega.wav");
		unlink($arrFilename["dirname"]."/".$arrFilename['filename']."legb.wav");
		//array_to_xml($arrData,$xml_data);
		//$result = $xml_data->asXML($arrFilename["dirname"]."/".$arrFilename['filename'].".xml");
	}
	
}

function actualizarCDR($audiomixed,$uniqueid,$oDB)
{
    //unlink($audiomixed);//Elimina grabacion wav
	$name = str_replace(".wav", ".mp3", $audiomixed);
	$sQuery = "UPDATE cdr SET recordingfile = ? WHERE uniqueid = ? ";
	$oDB->genQuery($sQuery,array($name,$uniqueid));
}


function array_to_xml( $data, &$xml_data ) {
    foreach( $data as $key => $value ) {
        if( is_array($value) ) {
            if( is_numeric($key) ){
                $key = 'item'.$key; //dealing with <0/>..<n/> issues
            }
            $subnode = $xml_data->addChild($key);
            array_to_xml($value, $subnode);
        } else {
            $xml_data->addChild("$key",htmlspecialchars("$value"));
        }
     }
}

<?php

# Required File Includes
include("../../../dbconnect.php");
include("../../../includes/functions.php");
include("../../../includes/gatewayfunctions.php");
include("../../../includes/invoicefunctions.php");

$gatewaymodule = "ifmb"; # Enter your gateway module name here replacing template

$GATEWAY = getGatewayVariables($gatewaymodule);
if (!$GATEWAY["type"]) die("Module Not Activated"); # Checks gateway module is active before accepting callback

class ifmb{
	protected $entity;
	protected $subentity;
	protected $backofficekey;
	protected $getpaymentsxmlurl="http://ifthensoftware.com/IfmbWS/IfmbWS.asmx/getPaymentsXML?chavebackoffice=%backofficekey%&entidade=%entity%&subentidade=%subentity%&dtHrInicio=%begindate%&dtHrFim=%enddate%&referencia=%reference%&valor=%value%";

	public function __construct($entity, $subentity, $backofficekey){

		$this->entity=(string)$entity;
		$this->subentity=(string)$subentity;
		$this->backofficekey=$backofficekey;

		$this->getpaymentsxmlurl=str_replace("%backofficekey%",$this->backofficekey,$this->getpaymentsxmlurl);
		$this->getpaymentsxmlurl=str_replace("%entity%",$this->entity,$this->getpaymentsxmlurl);
		$this->getpaymentsxmlurl=str_replace("%subentity%",$this->subentity,$this->getpaymentsxmlurl);
	}

	/**
	* getPayments() - get an array of associative arrays that describes a payments (id, value)
	* The dates should be in the format dd-MM-YY
	*/
	public function getPayments($begindate,$enddate){

		// set params
		$url=$this->getpaymentsxmlurl;
		$url=str_replace("%begindate%",$begindate,$url);
		$url=str_replace("%enddate%",$enddate,$url);
                $url=str_replace("%reference%","", $url);
                $url=str_replace("%value%","", $url);

		// get payments
		$xml = simplexml_load_file($url);
		$payments = array();
		foreach($xml->Ifmb as $payment)
		{	$payment->Valor = str_replace(",",".",$payment->Valor);
			$id=(string) $payment->Id[0][0];
			$value=(string) $payment->Valor[0][0];
			array_push($payments,array("id"=>$id,"value"=>$value));
		}

		return $payments;
	}

	/**
	* getPaymentsToday() - get payments from today's date
	*/
	public function getPaymentsToday(){
		return $this->getPayments(date("d-m-Y"),"");
	}

        /**
         * getPayment() - gets a payment data
         */
        public function getPayment($reference){
                // set params
		$url=$this->getpaymentsxmlurl;
		$url=str_replace("%begindate%", "", $url);
		$url=str_replace("%enddate%", "", $url);
                $url=str_replace("%reference%", $reference, $url);
                $url=str_replace("%value%", "", $url);

		// get payments
		$xml = simplexml_load_file($url);
		$payments = array();
		foreach($xml->Ifmb as $payment)
		{
                        // Currency comes in format XX,XX
                        $payment->Valor = str_replace(",",".",$payment->Valor);
                        $payment->ValorLiquido = str_replace(",",".",$payment->ValorLiquido);
			$id=(string) $payment->Id[0][0];
			$value=(double) $payment->Valor[0][0];
                        $fee = (double) $payment->Valor[0][0] - (double) $payment->ValorLiquido[0][0];
                        $reference = (string) $payment->Referencia[0][0];
			array_push($payments,array("id"=>$id,"value" => $value, "fee" => $fee, "reference" => $reference));
		}
                if (sizeof($payments) > 0){
                    return $payments[0];
                }
                else {
                    return false;
                }
        }
}



# Get Returned Variables
$antiphishingkey = $_GET["chave"];
$entity = $_GET["entidade"];
$reference = $_GET["referencia"];
$value = (double) str_replace(",", ".", $_GET["valor"]);

// Check that params are right
if($antiphishingkey == $GATEWAY["antiphishingkey"] && $entity == $GATEWAY["entity"]){

    $ifmb = new ifmb($GATEWAY["entity"], $GATEWAY["subentity"], $GATEWAY["backofficekey"]);
    $payment = $ifmb->getPayment($reference);

		// Check that the payment was really done
    if($payment && $payment["reference"] == (string)$reference && $value = $payment["value"]) {
        // Check invoice and transaction
        $invoiceid = checkCbInvoiceID($payment["id"],$GATEWAY["ifmb"]); # Checks invoice ID is a valid invoice number or ends processing
        $transid = "IFMB-#" . $invoiceid . "-" .  $value . "EUR";
        checkCbTransID($transid);

        // Everything is ok, let's add the payment
        $fee = $payment["fee"];
        $amount = $payment["value"];
        addInvoicePayment($invoiceid, $transid, $amount, $fee , $gatewaymodule); # Apply Payment to Invoice: invoiceid, transactionid, amount paid, fees, modulename
				logTransaction($GATEWAY["name"],$_GET,"Successful"); # Save to Gateway Log: name, data array, status

    } else {
				// Payment wasn't really done
				logTransaction($GATEWAY["name"],$_GET,"Unverified payment");
        http_response_code(400);
    }

} else {
	  logTransaction($GATEWAY["name"],$_GET,"Invalid params"); 
    http_response_code(400);
}
?>

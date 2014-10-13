<?php
function ifmb_config() {
    $url = $params['systemurl'];
    $configarray = array(
        "FriendlyName" => array("Type" => "System", "Value" => "IFmb"),
        "entity" => array("FriendlyName" => "Entidade", "Type" => "text", "Size" => "20",),
        "subentity" => array("FriendlyName" => "Subentidade", "Type" => "text", "Size" => "20",),
        "antiphishingkey" => array("FriendlyName" => "Chave Anti Phishing", "Type" => "text", "Size" => "20", "Description" => "Necessária para o callback. Forneça esta chave à IfthenPay, Lda por email e o URL [URL_WHMCS]/modules/gateways/callback/ifmb.php?chave=[CHAVE_ANTI_PHISHING]&entidade=[ENTIDADE]&referencia=[REFERENCIA]&valor=[VALOR]"),
        "backofficekey" => array("FriendlyName" => "Chave Backoffice", "Type" => "text", "Size" => "20", "Description" => "Chave fornecida pela IfthenPay, Lda na assinatura do contrato."),
    );
    return $configarray;
}

function ifmb_link($params) {

    # Gateway Specific Variables
    $gatewayentity = $params['entity'];
    $gatewaysubentity = $params['subentity'];

    # Invoice Variables
    $invoiceid = $params['invoiceid'];
    $amount = $params['amount']; # Format: ##.##

    $payment = ifmb_generate($invoiceid, $amount, $gatewayentity, $gatewaysubentity);

    # Render
    if ($payment){
      $code = ifmb_render($payment["entity"], $payment["reference"], $payment["value"]);
    } else {
      $code = ifmb_renderMessage("Valor inferior a 1 EUR");
    }
    return $code;
}

function ifmb_render($entity, $reference, $value) {
    $code = '<div class="ifmb" style=" overflow: auto;  ">';
    $code .= ifmb_logo();
    $code .= '<div class="details" style="display: inline-block; text-align: left; vertical-align: top;">';
    $code .= '<div class="row"><b>Entidade:</b> ' . $entity . '</div>';
    $code .= '<div class="row"><b>Referência:</b> ' . $reference . '</div>';
    $code .= '<div class="row"><b>Valor:</b> ' . $value . ' EUR</div>';
    $code .= '</div></div>';
    return $code;
}

function ifmb_renderMessage($message){
  $code = '<div class="ifmb" style=" overflow: auto;  ">';
  $code .= ifmb_logo();
  $code .= '<div class="details" style="display: inline-block; text-align: left; vertical-align: top; padding: 10px;">';
  $code .= '<div class="row"><b>Impossível gerar referência!</b></div>';
  $code .= '<div class="row">' . $message . '</div>' ;
  $code .= '</div></div>';
  return $code;
}

function ifmb_generate($order_id, $order_value, $ent_id, $subent_id) {

    $chk_val = 0;
    $order_id = "0000" . $order_id;
    $order_value = sprintf("%01.2f", $order_value);
    $order_value = ifmb_formatNumber($order_value);

    if ($order_value < 1)
        return false;

    if (strlen($subent_id) == 1) {
        $order_id = substr($order_id, (strlen($order_id) - 6), strlen($order_id));
        $chk_str = sprintf('%05u%01u%06u%08u', $ent_id, $subent_id, $order_id, round($order_value * 100));
    } else if (strlen($subent_id) == 2) {
        $order_id = substr($order_id, (strlen($order_id) - 5), strlen($order_id));
        $chk_str = sprintf('%05u%02u%05u%08u', $ent_id, $subent_id, $order_id, round($order_value * 100));
    } else {
        $order_id = substr($order_id, (strlen($order_id) - 4), strlen($order_id));
        $chk_str = sprintf('%05u%03u%04u%08u', $ent_id, $subent_id, $order_id, round($order_value * 100));
    }

    $chk_array = array(3, 30, 9, 90, 27, 76, 81, 34, 49, 5, 50, 15, 53, 45, 62, 38, 89, 17, 73, 51);
    for ($i = 0; $i < 20; $i++) {
        $chk_int = substr($chk_str, 19 - $i, 1);
        $chk_val += ($chk_int % 10) * $chk_array[$i];
    }
    $chk_val %= 97;
    $chk_digits = sprintf('%02u', 98 - $chk_val);

    $reference = substr($chk_str, 5, 3) . " " . substr($chk_str, 8, 3) . " " . substr($chk_str, 11, 1) . $chk_digits;

    $payment = array("entity" => $ent_id, "reference" => $reference, "value" => $order_value);
    return $payment;
}

function ifmb_formatNumber($number) {
    $verifySepDecimal = number_format(99, 2);
    $valorTmp = $number;
    $sepDecimal = substr($verifySepDecimal, 2, 1);
    $hasSepDecimal = True;
    $i = (strlen($valorTmp) - 1);

    for ($i; $i != 0; $i-=1) {
        if (substr($valorTmp, $i, 1) == "." || substr($valorTmp, $i, 1) == ",") {
            $hasSepDecimal = True;
            $valorTmp = trim(substr($valorTmp, 0, $i)) . "@" . trim(substr($valorTmp, 1 + $i));
            break;
        }
    }

    if ($hasSepDecimal != True) {
        $valorTmp = number_format($valorTmp, 2);
        $i = (strlen($valorTmp) - 1);

        for ($i; $i != 1; $i--) {
            if (substr($valorTmp, $i, 1) == "." || substr($valorTmp, $i, 1) == ",") {
                $hasSepDecimal = True;
                $valorTmp = trim(substr($valorTmp, 0, $i)) . "@" . trim(substr($valorTmp, 1 + $i));
                break;
            }
        }
    }

    for ($i = 1; $i != (strlen($valorTmp) - 1); $i++) {
        if (substr($valorTmp, $i, 1) == "." || substr($valorTmp, $i, 1) == "," || substr($valorTmp, $i, 1) == " ") {
            $valorTmp = trim(substr($valorTmp, 0, $i)) . trim(substr($valorTmp, 1 + $i));
            break;
        }
    }

    if (strlen(strstr($valorTmp, '@')) > 0) {
        $valorTmp = trim(substr($valorTmp, 0, strpos($valorTmp, '@'))) . trim($sepDecimal) . trim(substr($valorTmp, strpos($valorTmp, '@') + 1));
    }

    return $valorTmp;
}

function ifmb_logo(){
    $svg = '<div style="display: inline-block; height: 100px; width: 100px;"><img alt="Multibanco" width="100" height="100"
  src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAGQAAABkCAMAAABHPGVmAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAwBQTFRFYWBhUVBRq8fhWllaXVxdUovBLXO07Ozs6urq+Pj4ubi40NDQycjJk5KTWZHEZWRlZ5nJVY7CVVRVxcTFv76/bWxts7Ky5ubmscrj5eTkr66v2djZo6KjxdnqlZSVsbCxjIyMosHe29rb8fHxwsLCe3p7b25vcqHNUIrAaWdoPn67d3Z3dqPO8vb6rKys6vH3i4qLhazTqKiopaSlJm+yydvrfXx9g4KDkJCQ9/r8i7LV5+/24+LjUE9Qnp2emZiZRIK8j46Ph4aHg4GCdXR1Onu4VFNUhYSFf39/YF9gX15f/P3+TIW+vdLnpqamT05P9fX1Q0JDoKCgtbW1aGZncXFx/v7+zs7O0M/Q/f39RkVGamlq+/v76enp6Ojoq6urTk1OxMPE1tbWt7e3x8fHRENE8/PzRURFysrKR0ZHzczMWFdY9PT0bm1uc3Jz3d3dcG9w4eHhSUhJSEdI09LT09PTfHt8/Pz8y8vL9vb2xsbGeHd4S0pLvby9zMvLmJeYs7OzTUxN/f7+dHN0+vr6m5ubxMPDp6anq6qr8vLy9/f33NzczMzMI22w1NTU1dXVSklKvLy8XFtcvr2+mpmZwsHBpqWmZGNkqKeo1ePwoaChoaGhlJOT3NvcwsHC19fXtLS0aJrJcnFyU4zBgYCB19bXg6rS4ODgjIuMtra2aWhpnZ2d/v7/TEtMvby8o6Ojjo2Op6aml5aXfn1+vdPnxsXFmJeXW1pbh4eH4eDg+/z9lZOU8O/vvr29UE5Pqqmp397f9/b2iomK0dHR9fT1SIS+n77csbGx3+r0urm5n56fbp7LWZDDXJLFX5TG6ejpM3e2mpmaoJ+g2tnZnJuccnFxwL/AfqnRoqGikpGRzc3N5eXl+/r64N/f7/T5rKus+vv9bGpruc/ldnV26e/3lrnawcDA2NfXnJub6OfnfX191tXWy8rL5OPk2NfYurm6uLe4psPelZWV0+HvhoWGjIuLzczNt7a25O31qqqqzs3OiYiItMzkImywQkFC////PhKRCAAAAQB0Uk5T////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////AFP3ByUAAAXPSURBVHja7Jh5XJRFGMeXRBASMXSTvDLTVbwPjMTWI5VKi5ddeVfABRfkFAFFPBBTRFNRNMHS1DwwLa/EMlsruw+yssuyzC67paw07d6Z5pn32IV9/Tjzenyqzz5/vO88zzsz3/edmd+zM2vAl8EMPogP4oP4IJcN8kV1Yw57fYAOyO+NXZzW/FNeyMBDLm7rwgt5zKXD1nBCeuiBXMcJeQAaVf99B6O98jbU76sH0pt9lYZD/Qf1QEazQ9rphSwrY4cM0DNcfUmbQxyiHgiQcE5I7zciu77KkzpOdQvo8fmlz11lvlR/iSHJvQa3sDcMLtm0Mzjo4kEWzUMIrU6pH6z0I8FG6bRsa388XrUIU35pIjckGFF7uF7LtAwazJgCzizUwOal80LMcsv7PIP5cjCEfirysvf4IIlKux0ewcAkOXgbeLHeENSJC5KstvveHaxRYkkzFMi+sZJV9DOCa27CAxmrQqLUmM2gBlMUSJz7228FfzsPpFTtL6ZYiX3jHpYXvSE4ZSrxRR7IXfTjaYetldh+8Cw0lqABwVtgIG0cEPrtR1fDtUSZJnhTdHMOXJtqQW6EQBAHJJ4u33fpa0+TQgep0yoUrou1IFXEn8ozXIXQQ/pLAtza04jdH8r+uDPc7teA0GnszAORR74Obs9Mcq+3n/A9cOvuBYkLprN1hANioz1eKfdMp/4sFUgibq2oUYEsFCOISTrNncQBkbR4DGNIiGgziZzIglIHjGcqapQhaR6Cz0rjUTz9ggyS6NfRxs9h/JuijwRFjTKk1APin84DKZUmGeNJtPFrGDeCu4Hk5CKFpvElCPWxs0OoFudDaT6UHJknaRcHIUsqalTmJHXzOGLx8Q76YA875AZlneJs2nTfGTp+gRBR1Oilk0+cSsZhg0RAdSuUguAHEpno+txGnylq9ILgtwR5kNkgVItrafER94DfSwOKGr0huC2EMlkhHpmxwKIwDFLyU9SoAdkHoZmMEOl3cQ52vzlYrORfK688DUglhBYyQlQtgl0tM6YmS76kRk1Ie498el7In3QtzZCc4jAJMkJ+KKlRC9J0BYTsjBBVi9Qer79JkNT4sRcks8bs+fNzXohbi2BbpR2KsgOT1DhFgjgE1aQFYglkhbi1SG06uE+prqxGrS0RQruZFV+napFaCzLWMeWqu0OC1GogcmexJ8iPYIOa4PazO9+S7PbGwx7Djk/mNkQ4O5TzZOHUYcPOvbHFP+9/9CFyO9ByjKe1XOr7U+0/DunZ1dXtK329XRHgimaDdHG5XG/qgwSQpr8wQZqRms10MWzn+NPr4kOimSFX6YIM4oNEntLB6NkcIF8zQarp/4kjb+e2ANqwHRMk3HUhNvJJNjG+fCGQDxgVv2aZfsZQG2taGVQ9VBcnsmv0Lp7cVTZah+3ypXof5N8AyXSGEkVtNe+3hZGtT4rwrV8b+ZBrGRMD5zvz06TS6Rzz+ES8ajg5urcRAm/KIyfsw2miJbclG6QFPWR0QKgcTcT4bnQnOYdes9YaJlprFqMJhVbrCMsU/JdjWJRlJRbQXrJNQxsdzsHW2PXm0CHT0SYmyCoUUYVbZR1Hy90QYuOssHuc4IfxRJSQiP7A+KwBC070IYHUohOkhjXmAMZbjEyQbY4KtP4zv2z0qzbkzGz04zF0OHXDcPS8EPJOzthU1A8tIDV2msildRUTJN+IY0piTgdZsuFINw2tbAApteYa52RUwCRtFEKWdB+1SIYMziOXXqOYIGIfcjAkw96o1hCF8TrLsaTg+sOFv0O9/G0FHTuhBCEEX++cjSrp+X1V4RMYx09mgoT2xwuySNXJc7ejPJNlLt47WxSnL5EhSaIoCiWnoSu75QcCwcuT0NJQQRS/XO8fJq5eUcQEOdqRNEwhx/IjOK2uBLb3z5qMJcU15BxfMHxpntFomhu3oS3U3FMwBE4kRbuxzWoy5qfY+7//QhNfWvFBfBAf5H8C+UeAAQAmgVXUx5eIdQAAAABJRU5ErkJggg%3D%3D" />
</div>';
    return $svg;
}

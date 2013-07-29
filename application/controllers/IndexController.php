<?php
class IndexController extends Zend_Controller_Action
{

    public function init()
    {
            $ajaxContext = $this->_helper->getHelper('AjaxContext');
    	    $ajaxContext->addActionContext('list', 'html')
    	                ->addActionContext('modify', 'html')
    	                ->initContext();
        }

    // Function for read and write file
    public function isBuildTable($string)
    {
        /**
         *  Start with '</' --> end table
         *  End with '/>' --> end each row     
         *  Nothing --> start a table
         *  Return 
         *      No  : Rows value <tr>..
         *      Yes : end of each table </table>
         *      Other: Names of that table --> create <table>
        */
        $lastChars = substr($string,strlen(ltrim(rtrim($string)))-2,2);
        if($lastChars == '/>')
            return 'No';
        else
        {
            $firstChars = substr($string,0,2);
            if ($firstChars == "</") 
            {
               return "Yes";
            }
            else // return name
            {
                $name = ''; // edit to "" only
                if (strrchr($string,"Name") !=false)
                {
                    $posNameStart = strrpos($string,"Name")+6;// Name="
                    $posNameEnd   = strrpos($string,"\"",$posNameStart);
                    $name = substr($string,$posNameStart,$posNameEnd-$posNameStart);                    
                }
                return $name;                
            }            
        }
    }

    public function getDetailArrays($string,&$arrHead,&$arrValue)
    {
        /**
         * Purpost: each line read is converted into Heading and value
         * Return 
         *   Array 1 : Heading
         *   Array 2 : Value of that row
         */
        $string    = rtrim(ltrim($string));
        $lastChars = substr($string,strlen($string)-2,2);
        if($lastChars == '/>')
        {
            $curPos = 0;      
            while($curPos <= strlen($string)-5 )
            {       
                $strStart  = strpos($string," ",$curPos)+1;
                $strEnd    = strpos($string,"=",$strStart);
                $arrHead[] = substr($string,$strStart,$strEnd-$strStart);
                
                $valueStart = $strEnd+2;
                $valueEnd   = strpos($string,"\"",$valueStart);
                $arrValue[] = substr($string,$valueStart,$valueEnd-$valueStart);
                
                $curPos = $valueEnd;    
            }      
        }
    }
    
    public function buildHeading($arrHead,$arrValue)
    {
        /**
        * Purpose: Return html of heading ( "<tr> <td>..")
        * Return value:
        *    String of html
        **/
        $stringHeading ="<tr align='center' style='font-weight: bold;'>";
        foreach($arrHead as $heading)
        {
            $stringHeading.="<td>".$heading."</td>";
            
        }               
        return $stringHeading."</tr>";
    }
    
    public function buildRowValue($arrHead,$arrValue)
    {
        /**
         * Purpose: Return html of row value ( "<tr> <td>..") (Can not edit)
         * Return value:
         *    String of html
         **/
        $stringValue ="<tr>";
        foreach($arrValue as $value)
        {
            if($value=="")
            {
                $value="&nbsp";
            }
            $stringValue.="<td>".$value."</td>";            
        }               
        return $stringValue."</tr>";
        
    }
  
    public function buildTable($stringRead,&$isBuildHead)
    {
        $stringRead = rtrim(ltrim($stringRead));
        $result = $this->isBuildTable($stringRead);
        $tableInfo = "";
        
        if($result == "Yes")  // close table
        {
            $tableInfo.="</table";
        }
        else if($result == "No") // build rows
        {
            $arrHead  = array();
            $arrValue = array();
            $this->getDetailArrays($stringRead,$arrHead,$arrValue);
            if ($isBuildHead == 0)
            {
                $tableInfo  .=$this->buildHeading($arrHead,$arrValue); // heading
                $isBuildHead = 1;
            }                
            $tableInfo .=$this->buildRowValue($arrHead,$arrValue);
        }
        else  // create table
        {
            $tableInfo.="<h3>".$result."</h3><table border='1'>";
            $isBuildHead = 0;
        } 
        return $tableInfo;
    }
    
    public function writeFile($string,$type,$file)
    {           
        $filename = substr($file,0,strlen($file)-3).$type;
        file_put_contents("../files/html/".$filename,$string);  
    }    
    
    // End for read and write file
    
    // START MAKING TREE MENU
    public function getNameTreeMenu($string,$index)
    {
        /**
         * Purpose: Count to decide which one is parent, which one is child ( base on space)
         * Return : number of whitespace counting from the begining
         *          Fasle: in 2 cases we skip
         * Variable: $string is value of each line read from xml file and input Name of that line
         *           $index: index of line in read file
         * Skip line space and "</"
         * Priority:
         *    + <..>  vd: <Functionlist>
         *    + <... Name="">  vd: <Group Name="Admin">
         *    + <... Code  Description  > 
         */ 
         if(trim($string) == "" || substr(ltrim($string),0,2)=="</" )
            return false;
         else
         {
            if(($startCaption = strpos($string,"Caption"))!=false)
            {
                // Caption and Code
                $endCaption    = strpos($string,"\"",$startCaption+9); // 6 : Caption="
                $caption       = substr($string,$startCaption+9,$endCaption-$startCaption-9);
                
                $startCode = strpos($string,"Code")+6;
                $endCode   = strpos($string,"\"",$startCode);    
                $code      = substr($string,$startCode,$endCode-$startCode);  
                $nameCode  = $caption." (Code:".$code.")";                 
                
                $name  = "<form method='post'>";              
                $name .= "<input id='buttonId' type='submit' value='".$nameCode."' name='nameCode' />";
                $name .= "<input type='hidden' name='name' value='".$nameCode."' />";
                $name .= "<input type='hidden' name='line' value='".$index."' />";
                $name .= "</form>";
                
                return "<span style='font-size:12px'>".$name."</span>";
           }            
            else
            {              
                if(($startIndex = strpos($string,"Name"))!=false)
                {
                    $endIndex = strpos($string,"\"",$startIndex+6); // 6 : Name="
                    return "<span style='font-size:14px;font-weight:bold;padding-left:20px'>".substr($string,$startIndex+6,$endIndex-$startIndex-6)."</span><br>";
                }
                else 
                    if(strpos($string," ")==false)
                        return "<span style='font-size:18px;font-weight:bold'>".substr($string,1,strlen(trim($string))-2)."</span><br>";                   
              }
         }         
    }    
    
    public function makeTreeMenu($funcLines)
    {
        $result = "";
        $sizeLine = sizeof($funcLines);
        for($i=1;$i<$sizeLine;$i++)
        {               
            if(($name = $this->getNameTreeMenu($funcLines[$i],$i)) != false);
            {                  
               $result .= $name;
            }                     
        }
        return $result;        
    }
    // ENG MAKING TREE MENU
    
    
    //START MAKING CONTENT FROM CLICK MENU
    public function strRequestFlow()
    {
        return "<img width='550px' height='90px' src='img/flowRequest.jpg' />";
    }
    public function strResponseFlow()
    {
        return "<img width='550px' height='90px' src='img/flowResponse.jpg' />";
    }
    
    public function getValueField($field,$lines)
    {
       /**
        * Variables:
        *    $lines: lines of data read from xmlField file
        *    $field: field need to match in xmlField (get from data request or response)
        * Return: array Data of line contain that field
        */
        $len      = sizeof($lines);
        $arrHead  = array();
        $arrValue = array();
     
        for($i=2;$i<$len;$i++)
        {
            if(strpos($lines[$i],'Code="'.$field.'"') !=false )
            {
                $this->getDetailArrays($lines[$i],$arrHead,$arrValue);
                return $arrValue;
            }
        }
    } 
    
    
    public function strTransaction($strFields,$lines,$functionCode,$strType)
    {
        /**
         * Pupose: return string of request transaction( html code - table) 
         * Variable:
         *      $strFiled: string contain Field ( separate by "," get from MsgData of EcrFunc.xml)
         *      $lines : lines read from file
         *      $functionCode: Code read from EcrFunct.xml
         *      $strType: "Response" or "Request" 
         */
        $tableHtml = "<span style='font-weight:bold;font-size:14px'>".$strType." Transaction Function</span>";
        $tableHtml.="<table><tr><td valign='top'>STX</td><td valign='top'>LEN</td>";
        //Message header
        $header = "<table border='1'><tr><td align='center' colspan='5' style='font-weight:bold;font-size:13px'>Message Header</td></tr>";
        $header.= "<tr><td>Header Filter</td><td>Function Code</td><td>RFU</td><td>End of message</td><td>Separator</td></tr>";
        $header.= "<tr align='center'><td>0</td><td>".$functionCode."</td><td>00</td><td>0</td><td>1C</td></tr>";
        $header.="</table>";
        $tableHtml .="<td>".$header."</td>";
        // Data from strFields
        $fields      = explode(",",$strFields);
        $dataField   = "";
        $sizeOfField = sizeof($fields);
        if($sizeOfField>1)
        {
            for($i=0;$i<2;$i++)
            {
                if($i==0 || ($i==1 && $sizeOfField <3 ))
                {
                    $msgData   = $this->getValueField($fields[$i],$lines);
                    $msgHeader = "Message Data ".($i+1);
                }
                else
                {
                    $msgData   = array("...","...","...","...","...","...");
                    $msgHeader = "MessageData(cont'd....)";
                }                       
                $dataField .="<td><table border='1'><tr><td align='center' colspan='4' style='font-weight:bold;font-size:13px'>".$msgHeader."</td>";
                $dataField .="<tr><td>Field Code</td><td>Len(Byte)</td><td>Data</td><td>Separator</td></tr>"; 
                $dataField .="<tr align='center'><td>".$msgData[0]."</td><td>".$msgData[4]."</td><td>...</td><td>1C</td></tr>"; 
                $dataField .="</table></td>";
            }     
        }
        $tableHtml .=$dataField;
        $tableHtml .="<td valign='top'>ETX</td><td valign='top'>LRC</td></tr><table>";
        return $tableHtml;        
    }
    
    public function strHeaderDescription($functionCode,$strType)
    {
        /**
         * Purpose: return html str of Msg Header Description
         * Variable:
         *      $functionCode: get from EcrFunc.xml 
         *      $strType     : "Response" | "Request"
         */
         $tableHtml  = "<span style='font-weight:bold;font-size:14px'>Message Header Description</span>";
         $tableHtml .= "<table border='1'><tr align='center' style='font-size:13px'><th>Name</th><th>Attribute</th><th>Len(Bytes)</th><th>Remark</th></tr>";
         $tableHtml .= "<tr><td>Header filter</td><td>ASC</td><td>12</td><td>Defaulted to zero (30 30 ...) </td></tr>";
         $tableHtml .= "<tr><td>Function code</td><td>ASC</td><td>2</td><td>Value ".$functionCode."</td></tr>";
         if($strType=="Response")
            $tableHtml .= "<tr><td>Response code</td><td>ASC</td><td>2</td><td>Refer to Response code list</td></tr>";
         else
            $tableHtml .= "<tr><td>RUF</td><td>ASC</td><td>2</td><td>30 30 ( 00 )</td></tr>";
         
         $tableHtml .= "<tr><td>End of Message</td><td>ASC</td><td>1</td><td>Indicate the end of message Value '0' to indicate <br> no other messages for this Command </td></tr>";
         $tableHtml .= "<tr><td>Separator</td><td>HEX</td><td>1</td><td>Separator Value is: 1C</td></tr></table>";
         return $tableHtml; 
    }    
    
    public function strDataDescription($strFields,$lines)
    {
        /**
         * Purpose : return html str of Msg Data Description
         * Variable:
         *      $strFields: string contain Field ( separate by "," get from MsgData of EcrFunc.xml)
         *      $lines: lines read from file
         */ 
         if(strlen(trim($strFields)==0))
            return "";
         $tableHtml  = "<span style='font-weight:bold;font-size:15px'>Message Data Description</span>";
         $tableHtml .= "<table border='1'><tr align='center' style='font-size:14px'><th>Name</th><th>Attribute</th><th>Field Code</th><th>Len(Bytes)</th><th>Remark</th></tr>";
         $fields     = explode(',',$strFields);
         foreach($fields as $field)
         {
             $msgData    = $this->getValueField($field,$lines);
             $tableHtml .= "<tr><td>".$msgData[1]."</td><td>".$msgData[2]."</td><td>".$msgData[0]."</td><td>".$msgData[4]."</td><td>".$msgData[5]."</td></tr>";
         }
         $tableHtml .= "</table>";         
         return $tableHtml;
    }
    
    public function strContentHtml($name, $funcRequestDataArray, $lines)
    {
        /**
         * Purpose: return str html - flow of a page of function code page
         * Variable:
         *      $funcRequestDataArray : array data of active Function ( to get Data og that func exactly)
         *      $lines : lines read from ErcField.xml
         */
         $strHtml  = "<h2>".$name."</h2>";
         $strHtml .= "Purpose:".$funcRequestDataArray[5]."<br>";
         $strHtml .= $this->strRequestFlow()."<br>";
         $strHtml .= $this->strTransaction($funcRequestDataArray[3],$lines,$funcRequestDataArray[0],"Request")."<br>";
         $strHtml .= $this->strHeaderDescription($funcRequestDataArray[0],"Request")."<br>";
         $strHtml .= $this->strDataDescription($funcRequestDataArray[3],$lines)."<br>"."<br>";
         $strHtml .= $this->strResponseFlow()."<br>";
         $strHtml .= $this->strTransaction($funcRequestDataArray[4],$lines,$funcRequestDataArray[0],"Response")."<br>";
         $strHtml .= $this->strHeaderDescription($funcRequestDataArray[0],"Response")."<br>";
         $strHtml .= $this->strDataDescription($funcRequestDataArray[4],$lines)."<br>";
         $strHtml .= "Note:".$funcRequestDataArray[6]."<br>";
         return $strHtml;     
    }
    // END MAKING CONTENT FROM CLICK MENU
 
    // Convert to HTML FILE 
    public function writeHtmlTreeMenu($left,$right,$type,$code)
    {
        /**
         *  Purpose: write to html file (after changing link detail-> to support open by html)
         *  Variable:
         *      + Left: content on the left side
         *      + Right: content on the right side
         *      + Type: type of file
         *      + $code: code of function --> to name file: vd: 55.html
         **/         
        $strHtml  = '<head>
                        <style type="text/css">
                        td {
                            color: black;
                            font-size: 12px;
                        }                      
                        #buttonId{
                            background:none!important;
                            border:none;
                            color:blue;
                            text-decoration: none;
                            padding-left: 50px;
                        }
                        </style>
                    </head>';
        $strHtml .= '<table>
                        <tr>
                           <td bgcolor="#B7F7D9" style="width: 380px;" valign="top">'.$left.'</td>  ';
        $strHtml .=       '<td valign="top" style="padding-left:10px"> '.$right.'</td>
                        </tr>
                    </table>';
        $filename = $code.".".$type;
        try
        {
            file_put_contents("../files/html_menu/".$filename,$strHtml);
        }            
        catch(exception $e)
        {
            echo $e;
        }            
    }
    
    public function generateAction()
    {
        $data = $this->getRequest()->getParam('function');        
        $this->writeAllFileHtml();
    }
    
    public function writeAllFileHtml()
    {        
        //Tree menu
        $filePathFunc = "../files/EcrFunc.xml";        
        $funcLines  = file($filePathFunc);
        $left = "";
        for($i=1;$i<sizeof($funcLines);$i++)
        {               
            if(($name = $this->getNameTreeMenu($funcLines[$i],$i)) != false);
            {                  
               $left .= $name;
            }                     
        }     
        // change type of post data to get data --> for html only      (left tree menu)
        foreach($funcLines as $funcLine)
        {
            $arrValue = array();
            $this->getDetailArrays($funcLine,$arrHead,$arrValue);            
            if(sizeof($arrValue)>3)
            {
                $nameCode   = $arrValue[1]." (Code:".$arrValue[0].")" ;
                $strSearch  = "<input id='buttonId' type='submit' value='".$nameCode."' name='nameCode' />";
                $strReplace = "<a id='buttonId' href='".$arrValue[0].".html"."'>".$nameCode."</a>";
                $left       = str_replace($strSearch,$strReplace,$left);
            }
        }
        // write the index.html
        $this->writeHtmlTreeMenu($left,"","html","index");
        
        // Content on the right hand side of each page
        $fieldPathField = "../files/EcrField.xml";
        $fieldsLines    = file($fieldPathField);
        $sizeFunc       = sizeof($funcLines);
        foreach($funcLines as $funcLine)
        {
            $arrValue = array();
            $this->getDetailArrays($funcLine,$arrHead,$arrValue);            
            if(sizeof($arrValue)>3)
            {                
                $nameCode   = $arrValue[1]." (Code:".$arrValue[0].")" ;
                $right      = $this->strContentHtml($nameCode,$arrValue,$fieldsLines);
                $this->writeHtmlTreeMenu($left,$right,"html",$arrValue[0]);
            }            
        }            
        echo Zend_Json::encode(array("Test" => "Tester",'success' => true));        
    }
    // End: Convert to HTML FILE 
    
    // TREE MENU INDEX
    public function indexAction()
    {
        //Tree menu
        $filePathFunc = "../files/EcrFunc.xml";
        $funcLines  = file($filePathFunc);
        $treeMenu     = $this->makeTreeMenu($funcLines);
        
        //Content        
        $result ="";
        if($lineRequest = $this->getRequest()->getParam('line'))
        {
            $fieldPathField = "../files/EcrField.xml";
            $fieldsLines    = file($fieldPathField);
            $this->getDetailArrays($funcLines[$lineRequest],$arrHead,$arrValue);
            $result     = $this->strContentHtml($this->getRequest()->getParam('name'),$arrValue,$fieldsLines); 
        }
     //   $this->writeAllFileHtml();
        
        $this->view->viewResult = $result;
        $this->view->treeMenu   = $treeMenu;
    }
    
    
    /*
    // function for listing file --> xml to html
    public function indexAction()
    {
        $isBuildHead = 0;
        $directory = "../files/";
        $xmlFiles = glob($directory."*.xml");
        $tableInfo = "";
                
        $this->view->xmlFiles = $xmlFiles;
       
        if ($this->getRequest()->getParam('file')) {
            $data = $this->getRequest()->getParam('file');            
            $filePath = "../files/".$data;
            //read file - omit the 1st line           
            $lines = file($filePath);
         
            for($i=1;$i<sizeof($lines);$i++)
            {               
                 $tableInfo .= $this->buildTable($lines[$i],$isBuildHead);
            }
            $this->view->tableInfo = $tableInfo;            
            $this->writeFile($tableInfo,"html",$data);
        }
    }
    */
    
    
    
	public function addAction()
    {
        $form = new Application_Form_Albums();
        $form->submit->setLabel('Add');
        $this->view->form = $form;
        
        if ($this->getRequest()->isPost()) {
            $formData = $this->getRequest()->getPost();
            if ($form->isValid($formData)) {
                $artist = $form->getValue('artist');
                $title = $form->getValue('title');
                $albums = new Application_Model_DbTable_Albums();
                $albums->addAlbum($artist, $title);
                
                $this->_helper->redirector('index');
            } else {
                $form->populate($formData);
            }
        }
    }
	public function editAction()
    {
        $form = new Application_Form_Albums();
        $form->submit->setLabel('Save');
        $this->view->form = $form;
        
        if ($this->getRequest()->isPost()) {
            $formData = $this->getRequest()->getPost();
            if ($form->isValid($formData)) {
                $id = (int)$form->getValue('id');
                $artist = $form->getValue('artist');
                $title = $form->getValue('title');
                $albums = new Application_Model_DbTable_Albums();
                $albums->updateAlbum($id, $artist, $title);
                
                $this->_helper->redirector('index');
            } else {
                $form->populate($formData);
            }
        } else {
            $id = $this->_getParam('id', 0);
            if ($id > 0) {
                $albums = new Application_Model_DbTable_Albums();
                $form->populate($albums->getAlbum($id));
            }
        }
    }
	
	public function deleteAction()
    {
        if ($this->getRequest()->isPost()) {
            $del = $this->getRequest()->getPost('del');
            if ($del == 'Yes') {
                $id = $this->getRequest()->getPost('id');
                $albums = new Application_Model_DbTable_Albums();
                $albums->deleteAlbum($id);
            }
            $this->_helper->redirector('index');
        } else {
            $id = $this->_getParam('id', 0);
            $albums = new Application_Model_DbTable_Albums();
            $this->view->album = $albums->getAlbum($id);
        }
    }
}


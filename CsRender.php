<?php
class CsRender{
    public function render($tpl, $params):string
    {
        $dom = new DomDocument("1.0", "UTF-8");
        $dom->loadHTMLFile($tpl);
        $this->renderDomNode($dom, $params);
        return $dom->saveHTML();
    }

    public function renderDomNode(DomNode &$domNode, array $params): void
    {
        // https://secure.php.net/manual/zh/domnode.removechild.php notes: NO.1
        // 在遍历中移除节点,会导致dom树重构,遍历终止
        // 所以采取将要移除的节点单独存储的方式
        $elementsToRemove = [];
        $if_stack = [];

        foreach ($domNode->childNodes as $item) {
            if ($item->nodeType == XML_ELEMENT_NODE && $if_value = $item->getAttribute("p-if")) {
                $if_result = $params[$if_value] ?? false;
                array_push($if_stack, $if_result);

                if ($if_result) {
                    $item->removeAttribute("p-if");
                } else {
                    array_push($elementsToRemove, $item);
                }
            }

            if ($item->nodeType == XML_ELEMENT_NODE && $item->hasAttribute("p-else")) {
                $else_result = array_pop($if_stack);

                if (!$else_result) {
                    $item->removeAttribute("p-else");
                } else {
                    array_push($elementsToRemove, $item);
                }
            }

            if ($item->nodeType == XML_ELEMENT_NODE && $for_value = $item->getAttribute("p-for")) {
                preg_match("/\((.*?), (.*?)\) in (.*)/", $for_value, $matches);
                [, $value, $index, $items] = $matches;

                foreach($item->childNodes as $el){
                    array_push($elementsToRemove, $el);
                }
                // https://secure.php.net/manual/zh/domnode.appendchild.php notes NO.2
                // 和移除节点一样, 需要把要加入的节点单独记录
                $elementsToAppend = [];
                foreach($params[$items] as $k=>$v){
                    $for_runtime_params = $params;
                    $for_runtime_params[$value] = $v;
                    $for_runtime_params[$index] = $k;
                    foreach($item->childNodes as $el){
                        $e = $el->cloneNode(true);
                        if ($e->hasChildNodes()) {
                            $this->renderDomNode($e, $for_runtime_params);
                        }
                        array_push($elementsToAppend, $e);
                    }
                }
                foreach($elementsToAppend as $el){
                    $item->appendChild($el);
                }

                $item->removeAttribute("p-for");
            }

            if ($item->nodeType == XML_TEXT_NODE) {
                $str             = preg_replace_callback('/\{\{ *(.*?)(\[.*?\]) *\}\}/', function ($matches) use ($params) {
                    $k = 'return $params["'.$matches[1].'"]';
                    for($i=2; $i<count($matches);$i++){
                        $k .= $matches[$i];
                    }
                    $k .= ';';
                    return eval($k);
                }, $item->nodeValue);
                $item->nodeValue = $str;
            }

            if ($item->hasChildNodes()) {
                $this->renderDomNode($item, $params);
            }
        }

        foreach ($elementsToRemove as $item) {
            $item->parentNode->removeChild($item);
        }
    }
}
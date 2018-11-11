## 使用DOM解析来实现PHP模版引擎

### 0. 前言: 传统模版语法的不利之处

目前市面上有很多PHP的模版引擎, 如smarty、blade等. 其中大部分都是基于正则表达式将其中的模版语法转换成PHP代码, 并进行缓存. 模版代码所经历的过程如下:

```
template -> php -> html
```

使用正则替换或者直接使用PHP原生有什么问题呢? 以下我们以blade为例来看一些具体例子:

```php
<html>
<body>
    <div>
        <div class="items" >
            @if (count($records) === 1)
                <p>我有一个记录！</p>
            @elseif (count($records) > 1)
                <p>我有多个记录！</p>
            @else
                <p>我没有任何记录！</p>
            @endif
        </div>
    </div>
</body>
</html>
```
#### 问题一: 编辑器格式化和语法高亮的问题

如上, 我们面临的第一个问题是html和blade语法混杂在一起. 在阅读逻辑上, 我们需要来回的在blade和html之间做转化.
当然, 当你熟悉了blade的语法并熟练掌握这个能力的时候, 这种转化并不会对你的阅读构成障碍.
但是, 对于编辑器来说, 如果不使用合适的插件, 无论是代码高亮还是自动格式化都会产生意想不到结果

#### 问题二: html中渲染class等属性

其实以上还不是最令人眼花缭乱的,在我有限的工作经历中, 使用PHP渲染html中的class或者其他属性时, 经常会看到如下令人恐怖的代码

```html
<html>
<body>
    <div>
        <ul class="items" >
            <li <?= $cur==1 ? 'class="active"' : ''?>>NO.1</li>
            <li <?= $cur==2 ? 'class="active"' : ''?>>NO.2</li>
            <li <?= $cur==3 ? 'class="active"' : ''?>>NO.3</li>
            <li <?= $cur==4 ? 'class="active"' : ''?>>NO.4</li>
        </ul>
    </div>
</body>
</html>
```

以上还不是最恐怖的, 当有的人既不使用`<?= ?>`又不使用三元运算时...简直不可想象.

#### 问题三: 公共模版中代码代码的不完整

对于大部分网页的头部和尾部,我们单独抽离出来以供复用. 对于blade这种支持类似插槽的模版引擎,情况并不算太糟, 单对于不支持类似特性的模版引擎, 如下的代码也是非常常见

```php
#./header.phtml 头文件
<html>
<body>
    <div class="nav">

    </div>
    <div class="content">
```

```php
#./bottom.phtml 尾文件
    </div>
    <div class="bottom">

    </div>
</body>
</html>
```

如上的问题在于什么呢, 每个部分模版都不是标签闭合的,每一部分并不完整. 在独立模版存在非常多的情况下, 正确的让html标签闭合也成为开发负担之一.

好了, 说完了这么多问题, 我们来想一想是否有解决的办法. 要知道以前前端js代码合并也是基于正则, 但是新的三大框架都是基于dom解析来实现. 那如果说, 我们在写php渲染页面的时候也可以和Vue一样, 使用类似如下的语法, 是不是就能解决以上的问题呢? 当然本文只是给大家提供一个最基本的思路, 和最基础的实现, 仅供娱乐和思路拓展吧.

```html
<!-- ./tpl.html -->
<html>
<body>
    <div>
        <div class="title">
            <div p-if="is_author">
                <p>{{ author }}</p>
            </div>
            <div p-else>
                <p>{{ vistor }}</p>
            </div>
        </div>

        <div p-for="(value, idx) in items">
            <p>{{ value }} - {{ idx }}</p>
            <p>{{ value }}</p>
        </div>
    </div>
</body>
</html>

```
```php
$params = [
    "is_author" => true,
    "author"    => "liangwt",
    "vistor"    => "Welcome",
    "items"     => [
        "A",
        "B",
        "C",
    ],
];

csRender("./tpl.html", $params);
```

```html
<!-- out -->
<html>
<body>
    <div>
        <div class="title">
            <div>
                <p>liangwt</p>
            </div>
            <div>
                <p>Welcome</p>
            </div>
        </div>
        <div>
            <p>A - 0</p>
            <p>A</p>
            <p>B - 1</p>
            <p>B</p>
            <p>C - 2</p>
            <p>C</p>
        </div>
    </div>
</body>
</html>
```

### 1. DOM基本知识

- D: Document 代表里文档
- O: Object 代表了对象
- M: Model 代表了模型

DOM把整个文档表示为一棵树, 确切的说是一个家谱树. 家谱树中我们使用 parent(父)、child(子)、sibling(兄弟)来描述成员之间的关系.

对于一个普通的如下的xml来说
```xml
<?xml version="1.0" encoding="utf-8"?>

<bookstore>
    <book category="children">
          <title lang="en">Harry Potter</title> 
          <author>J K. Rowling</author> 
          <year>2005</year> 
          <price>29.99</price> 
    </book>

    <book category="cooking">
          <title lang="en">Everyday Italian</title> 
          <author>Giada De Laurentiis</author> 
          <year>2005</year> 
          <price>30.00</price> 
    </book>

    <book category="web">
          <title lang="en">Learning XML</title> 
          <author>Erik T. Ray</author> 
          <year>2003</year> 
          <price>39.95</price> 
    </book>

    <book category="web">
          <title lang="en">XQuery Kick Start</title> 
          <author>James McGovern</author> 
          <author>Per Bothner</author> 
          <author>Kurt Cagle</author> 
          <author>James Linn</author> 
         <author>Vaidyanathan Nagarajan</author> 
          <year>2003</year> 
          <price>49.99</price> 
    </book>

</bookstore>
```

我们可以生成如下的dom树结构

![](https://wx1.sinaimg.cn/bmiddle/b373c093ly1fx44xj1xgej20g1083gli.jpg)

示例来源于[知乎](https://www.zhihu.com/question/34219998/answer/158008758)

### 2. PHP中DomDocument的使用

PHP中原生提供了xml文档解析的拓展, 它使用起来非常简单. 网上资料大多介绍基于此拓展的封装包, 因此这里稍微详细介绍下.

#### (1). DOM中的基类节点: The DOMNode class 

前面介绍dom树的时候说过, 文档是由不同类型的节点构成的集合, 所以DomDocument中绝大多数的类都继承于此.

它的类属性除了描述了自身名称(`$nodeName`)、值(`$nodeValue`)、类型(`$nodeType`)等, 还描述了其父节点(`$parentNode`)、子节点(`$childNodes`)、同级节点(`$previousSibling`、`$nextSibling`)等.

它的类方法除了包括对子节点的插入(`appendChild()`)、替换(`replaceChild()`)、 移除(`removeChild()`)之外,还有诸多用于判断自身属性的函数.

作为任何类型的节点基类我们需要重点关注它的每一个属性和方法,参考[官方文档](https://secure.php.net/manual/zh/class.domnode.php).

#### (2). 整个文档: DOMDocument extends DOMNode 

DOMDocument继承自DOMNode, 它代表了整个文档, 也是整个文档树的根结点. 其中继承自基类的属性`$nodeType`是`XML_DOCUMENT_NODE(9)`

我们通常使用它的`load*()`来创建dom树,和`save*()`系列方法将dom转换成文本

我们的代码也是如此开头和结束

```php
function csRender(string $tpl, array $params)
{
    $dom = new DomDocument("1.0", "UTF-8");
    $dom->loadHTMLFile($tpl);
    // ...
    echo $dom->saveHTML();
}
```

#### (3). 元素节点 DOMElement extends DOMNode

DOMElement继承自DOMNode, 它代表了<p> <body>之类的标签, 是构成dom结构的基本节点.其中标签的名字就是节点的属性`tagName`, 它的`$nodeType`是`XML_ELEMENT_NODE = 1`

元素可以包含其他的元素, 元素节点中也包含了其他类型的节点.

我们可以使用`getAttributeNode()` 或者`getAttribute()` 来获取元素节点的属性或者属性名,使用`getElementsByTagName(string $name)`获取元素包含的标签名`$name`为的节点.以及使用`remove*()`和`set*()`函数来删除和修改指定属性

我们在实现上面p-if的时候需要进行判断if条件是否成立,并在之后删除掉这个属性
```php
if ($item->nodeType == XML_ELEMENT_NODE 
    && $if_value = $item->getAttribute("p-if") {
    if ($if_result) {
       $item->removeAttribute("p-if");
    }
}
```

#### (4). 属性节点 DOMAttr extends DOMNode

DOMAttr继承自DOMNode, 它代表了标签`class="one"`之类的属性, 如上面所讲对元素节点调用`getAttributeNode()`即可获取此元素的属性节点. 属性节点的nodeType是XML_ATTRIBUTE_NODE=2


#### (5). 文本节点 DOMText extends DOMCharacterData 

DOMText继承自DOMCharacterData, DOMCharacterData也是继承自DOMNode. 在dom中它代表了元素节点包含的文本.其中nodeValue属性就是文本的内容. 文本节点的nodeType 是XML_TEXT_NODE = 3 

除此之外需要知道的是, 文本节点单总是被包含在元素节点中, 文本节点的父节点是元素节点. 我们通过$elementNode->childNodes即可获取(如果有文本节点的话), 此函数返回的是 DOMNodeList 类型,它代表节点集合, 并实现了Traversable接口

我们在实现mustache语法的时候需要判断元素的文本节点中是否有{{}}包裹的变量
```php
if ($item->nodeType == XML_TEXT_NODE) {
    $str             = preg_replace_callback('/\{\{(.*?)\}\}/', function ($matches) use ($params) {
        // ...处理逻辑
    }, $item->nodeValue);
    $item->nodeValue = $str;
}
```

#### (6). 节点遍历

以上就是最常用的几种节点类型了, 我们下面讲一讲如何进行节点遍历.我们需要基于遍历去实现树中节点判断, 然后进行树操作

我们在上面介绍了如何加载一个html文档,其中获取的变量`$dom`也是dom树的根结点
```php
function csRender(string $tpl, array $params)
{
    $dom = new DomDocument("1.0", "UTF-8");
    $dom->loadHTMLFile($tpl);
    traversingtDomNode($dom, $params);
    echo $dom->saveHTML();
}
```
拥有一个节点之后如何遍历它的子节点呢, 我们获取其$domNode->childNodes子属性进行遍历即可

```php
function traversingtDomNode($dom, $params){
    foreach ($domNode->childNodes as $item) {
        //...
    }
}
```

在遍历每一个节点过程中, 可以通过判断nodeType来对不同类型节点进行操作. 同时如果此节点依旧有子节点, 我们继续把节点放入此函数进行递归调用
```php
function traversingtDomNode($dom, $params){
    foreach ($domNode->childNodes as $item) {
        if ($item->nodeType == XML_ELEMENT_NODE 
            && $if_value = $item->getAttribute("p-if")) {
            // ...
        }

        if ($item->nodeType == XML_ELEMENT_NODE 
            && $item->hasAttribute("p-else")) {
            // ...
        }

        if ($item->hasChildNodes()) {
            traversingtDomNode($item, $params);
        }
    }
}
```

### 3. mustache语法实现

`{{ key }}` 语法实现很简单, 我们只要通过正则拿到`{{ key }}`中的key值, 然后把连着{{ }}一起替换成`$params[$key]`即可

```php
// ...
    if ($item->nodeType == XML_TEXT_NODE) {
        $str = preg_replace_callback('/\{\{(.*?)\}\}/', function ($matches) use ($params) {
            return $params[trim($matches[1])];
        }, $item->nodeValue);
        $item->nodeValue = $str;
    }
// ...
```

### 4. if语法实现

```html
<div p-if="is_author">
    <p>{{ author }}</p>
</div>
```

if语法实现也很简单, 我们通过`$if_value =$item->getAttribute("p-if")`获取属性值, 并通过判断`$params[$if_value`]`的值, 如果成立,则删掉属性, 展示此元素节点. 如果不成立则删掉此节点.
```php
// ...
    if ($item->nodeType == XML_ELEMENT_NODE && $if_value = $item->getAttribute("p-if")) {
        $if_result = $params[$if_value] ?? false;
        if ($if_result) {
            $item->removeAttribute("p-if");
        } else {
            array_push($elementsToRemove, $item);
        }
    }
// ... 
```
注意这里面有个小坑: 参考文档中的一条评论:[notes: NO.1](https://secure.php.net/manual/zh/domnode.removechild.php)
在遍历中移除节点,会导致dom树重构,遍历终止. 所以我们采取将要移除的节点单独记录的方式,在循环结束后统一移除

```php

    $elementsToRemove = [];
    foreach ($domNode->childNodes as $item) {
        // ..
    }
    foreach ($elementsToRemove as $item) {
        $item->parentNode->removeChild($item);
    }
```

### 5. eles语法实现

```html
<div p-if="is_author">
    <p>{{ author }}</p>
        <div p-if="show_intro">
            <p>{{ intro }}</p>
        </div>
    </div>
    <p p-else>
        {{ vistor }}
    </p>
</div>
```

else 的实现会用到很有意思的技巧, 因为else的真值并不取决于它自身, 而是取决于和它配对的if的值. 注意!是和它**配对**的if值, 如果你想当然的认为是else之前的那个if值可就错咯. 我们看下面这个例子:

```html
<div p-if="is_author">
    <p>{{ author }}</p>
        <div p-if="show_intro_one">
            <p>{{ intro_one }}</p>
        </div>
        <div p-if="show_comment_one">
            <p>{{ comment_one }}</p>
        </div>
        <div p-else>
            <p>{{ comment_two }}</p>
        </div>
        <div p-else>
            <p>{{ intro_two }}</p>
        </div>
    </div>
</div>
```
其中最后一个else属性的值取决于第一个if "show_intro_one" 的值,即`$params[$if_value]`的值.那如何才能实现if-else正确的匹配呢, 答案就是: 栈. 在我们实现括号匹配, if-else匹配得各种匹配问题中, 栈是一个非常好的思路.

我们第一步需要在dom树同一深度给予不同栈, 因为if-else的匹配只会发生在同级元素直接, 而不会发生在父子元素之间.

第二步自然是每遇到一个if就把值放入对应栈的栈顶.

第三步在遇到else时, 从栈顶取出一个值, 它的反值即为else的值

```php
    foreach ($domNode->childNodes as $item) {
        // 1. 第一步
        $if_stack = [];
        // ...
        if ($item->nodeType == XML_ELEMENT_NODE 
            && $if_value = $item->getAttribute("p-if")) {
            $if_result = $params[$if_value] ?? false;
            // 第二步
            array_push($if_stack, $if_result);
            // ...
        }

        if ($item->nodeType == XML_ELEMENT_NODE && $item->hasAttribute("p-else")) {
            // 第三步
            $if_result = array_pop($if_stack);
            if (!$if_result) {
                $item->removeAttribute("p-else");
            } else {
                array_push($elementsToRemove, $item);
            }
        }
    }
```

### 6. for语法实现

```html
<div p-for="(value, idx) in items">
    <p>{{ value }} - {{ idx }}</p>
    <p>{{ value }}</p>
</div>
```

for的语法实现思路很简单, 把含有属性p-for属性的元素所有子节点按照遍历的数组循环赋值即可. 其中稍有难度的就是`$params`中的值传递问题, 或者说`$params`值的作用域问题, 如果恰好$params中也有个字段叫value或者idx, 但很明显在for的子节点中,value和idx应该是局部作用域, 他们需要在每次循环开始赋予新值, 并在整个循环结束后被销毁.

所以我们让一个新值`$for_runtime_params`等于外部`$params`参数, 并在循环中继续递归调用遍历函数
```php
if ($item->nodeType == XML_ELEMENT_NODE 
    && $for_value = $item->getAttribute("p-for")) {
    preg_match("/\((.*?), (.*?)\) in (.*)/", $for_value, $matches);
    [, $value, $index, $items] = $matches;

    foreach ($params[$items] as $k => $v) {
        $for_runtime_params = $params;
        $for_runtime_params[$value] = $v;
        $for_runtime_params[$index] = $k;
        foreach ($item->childNodes as $el) {
            $e = $el->cloneNode(true);
            if ($e->hasChildNodes()) {
                traversingtDomNode($e, $for_runtime_params);
            }
        }
    }
}
```

注意: 和删除节点一样, 我们在遍历的过程中也不能插入新节点, 他会导致获取的子节点永远为空. 所以也和删除一样单纯记录最后统一插入即可

### 7. 后记

本文实现肯定还有诸多细节未考虑, 但是给大家提供一个不错的思路. 对于未来可以尝试继续实现`v-class`语法, `slot`功能, `components`功能, 都是相当不错的

更详细的实现可以可以查看我的github: [cs-render](https://github.com/liangwt/cs-render)

同时也欢迎在我的[博客-showthink](https://blog.showthink.cn)阅读更多其他文章

也可以关注我的微博[@不会凉的凉凉](https://www.weibo.com/u/3010707603)与我交流
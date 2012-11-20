<?php


// No direct access.
defined('_JEXEC') or die;

$app= JFactory::getApplication();

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="<?php echo $this->language; ?>" lang="<?php echo $this->language; ?>" dir="<?php echo $this->direction; ?>" >
<head>

    <jdoc:include type="head" />
    <link rel="stylesheet" href="<?php echo $this->baseurl ?>/templates/<?php echo $this->template; ?>/css/reset.css" type="text/css" />
    <link rel="stylesheet" href="/templates/system/css/system.css" type="text/css" />

    <link rel="stylesheet" href="<?php echo $this->baseurl ?>/templates/<?php echo $this->template; ?>/css/template.css" type="text/css" />
</head>



<body id="bg" >

<div id="head" class="inner">
    <div id="header">

        <div id="logo">
            <h1><a href="">GeekHub</a></h1>
        </div>


        <div id="mainmenu">
            <jdoc:include type="modules" name="position-1" style="rounded"/>
        </div>


        <div id="links_container">
            <jdoc:include type="modules" name="position-6" style="rounded"/>
        </div>

    </div>

    <div id="main_massage">
        <jdoc:include type="modules" name="position-7" style="rounded"/>
    </div>


</div>


<div id="wrap">
    <div id="main_intro">
        <jdoc:include type="modules" name="position-8" style="rounded"/>
    </div>



        <div id="content">
            <jdoc:include type="component" />

        </div>
    <div style="width: 100%; clear: both;"></div>

    <div id="infowrap">
        <div id="vk" class="inner" >
            <jdoc:include type="modules" name="position-5" style="rounded"/>
        </div>
        <div id="sponsors" class="inner">
            <jdoc:include type="modules" name="position-3" style="rounded"/>
        </div>
        <div id="sertificates" class="inner">
            <jdoc:include type="modules" name="position-12" style="rounded"/>
        </div>

    </div>

    <div id="footermenu">
        <jdoc:include type="modules" name="position-11" style="rounded"/>
    </div>




    </div>



	</body>
</html>

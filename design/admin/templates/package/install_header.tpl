{* DO NOT EDIT THIS FILE! Use an override template instead. *}
    <input type="hidden" name="InstallerType" value="{$installer.type|wash}" />
    <input type="hidden" name="InstallStepID" value="{$current_step.id|wash}" />
    <input type="hidden" name="PackageStep" value="1" />


{* DESIGN: Header START *}<div class="box-header"><div class="box-tc"><div class="box-ml"><div class="box-mr"><div class="box-tl"><div class="box-tr">

<h1 class="context-title">{'Package install wizard: %wizardname'|i18n( 'design/admin/package',, hash( '%wizardname', $package.name|wash ) )}</h1>

{* DESIGN: Mainline *}<div class="header-mainline"></div>

{* DESIGN: Header END *}</div></div></div></div></div></div>

{* DESIGN: Content START *}<div class="box-ml"><div class="box-mr"><div class="box-content">

<div class="context-attributes">

            {let thumbnail_list=$package.thumbnail-list}
            {if $thumbnail_list}
            <div class="thumbnail">
                <img src={concat( $package|ezpackage( fileitempath, $thumbnail_list[0] ) )|ezroot} alt="{$thumbnail_list[0].name|wash}" />
            </div>
            {/if}
            {/let}


    <h2>{$current_step.name|wash}</h2>

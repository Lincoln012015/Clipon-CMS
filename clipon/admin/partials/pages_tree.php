<div class="tree-view" id="page-tree-root"></div>
<div id="tree-templates-source" style="display:none;" aria-hidden="true">
    <?php
    renderDirTemplates($rootDirs);
    foreach ($pages as $templatePage) {
        renderPageTemplate($templatePage);
    }
    ?>
</div>

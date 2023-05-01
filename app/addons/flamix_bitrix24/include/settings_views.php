<?php
    use Flamix\Helpers;
?><h2>Configurations</h2>
<ul>
    <li>
        Status -
        <?php
        try {
            if(method_exists('\Flamix\Helpers', 'send'))
                $status = Helpers::send(['status' => 'check'], 'check');
            else
                $email = false;

            if (!empty($status) && $status['status'] == 'success'): ?>
                <span style="color: #46b450;">Working</span>
            <?php else: ?>
                <span style="color: #dc3232;">Bad Domain or API Key</span>
            <?php endif; ?>
        <?php } catch (\Exception $e) { ?>
            <span style="color: #dc3232;"><?php echo $e->getMessage(); ?></span>
        <?php } ?>
    </li>
    <li>
        CURL -
        <?php if (extension_loaded('curl')): ?>
            <span style="color: #46b450;">Enable</span>
        <?php else: ?>
            <span style="color: #dc3232;">Disabled</span>
        <?php endif; ?>
    </li>
    <li>
        PHP version 7.4+ (Catch and send UTM tags and Trace Page) -
        <?php if (version_compare(PHP_VERSION, '7.4.0') >= 0): ?>
            <span style="color: #46b450;">PHP <?php echo PHP_VERSION;?></span>
        <?php else: ?>
            <span style="color: #dc3232;">Bad PHP version (<?php echo PHP_VERSION;?>). Update on your hosting</span>
        <?php endif; ?>
    </li>
    <li>
        Backup email -
        <?php
        /**
         * Когда активируешь модуль, то он не init.php и выдается ошибка
         */
        if(method_exists('\Flamix\Helpers', 'get_backup_email'))
            $email = Helpers::get_backup_email();
        else
            $email = false;

        if($email): ?>
            <span style="color: #46b450;">Valid (<?=$email; ?>)</span>
        <?php else: ?>
            <span style="color: #dc3232;">Invalid</span>
        <?php endif; ?>
    </li>
</ul>

<iframe width="560" height="315" src="https://www.youtube.com/embed/9Am5eGp0jAk" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
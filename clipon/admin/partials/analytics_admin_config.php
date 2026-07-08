    <script>
        const allStats = <?= json_encode($stats, JSON_UNESCAPED_UNICODE) ?>;
        const fromDate = '<?= $from ?>';
        const toDate = '<?= $to ?>';
        const copyIcon = <?= json_encode(Icons::copy(16)) ?>;
        const checkIcon = <?= json_encode(Icons::check(16)) ?>;
    </script>
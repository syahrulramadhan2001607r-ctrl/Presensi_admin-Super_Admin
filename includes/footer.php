        </main>
        <footer class="footer">
            &copy; <?= date('Y') ?> <?= e(APP_NAME) ?> &middot; Panel Admin &amp; Super Admin
        </footer>
    </div>
</div>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
<?php foreach (($extraJs ?? []) as $js): ?>
<script src="<?= e(url($js)) ?>"></script>
<?php endforeach; ?>
</body>
</html>

<script {csp-script-nonce}>
    // SHOW/HIDE password toggle. Delegated, so the register form's two
    // password fields are covered by the same handler.
    document.addEventListener('click', function (event) {
        var button = event.target.closest('.auth-reveal');
        if (!button) {
            return;
        }
        var field = document.getElementById(button.dataset.reveal);
        var shown = field.type === 'text';
        field.type = shown ? 'password' : 'text';
        button.textContent = shown ? 'SHOW' : 'HIDE';
    });
</script>

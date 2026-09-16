/*
 * The provider select on the git token screen. The page already works without
 * this: the first option is selected and its steps block is the one not hidden.
 */
(function () {
    var select = document.getElementById('provider');
    if (!select) {
        return;
    }

    var icon = document.getElementById('provider-icon');
    var label = document.querySelector('.field .field-label[for="secret"]');
    var field = document.getElementById('secret');

    function apply() {
        var option = select.options[select.selectedIndex];
        if (!option) {
            return;
        }

        if (icon) { icon.style.backgroundImage = "url('" + option.dataset.icon + "')"; }
        if (label) { label.textContent = option.dataset.field; }
        if (field) { field.placeholder = option.dataset.placeholder; }

        var blocks = document.querySelectorAll('[data-provider-steps]');
        for (var i = 0; i < blocks.length; i++) {
            blocks[i].hidden = blocks[i].dataset.providerSteps !== option.value;
        }
    }

    select.addEventListener('change', apply);
    apply();
})();

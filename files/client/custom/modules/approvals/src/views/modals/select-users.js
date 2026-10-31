define('approvals:views/modals/select-users', ['views/modals/select-records'], (SelectRecordsModalView) => {

    const CLASS_NAME = 'approves-select-users';

    /**
     * EspoCRM's standard 'Select Records' dialog, used to choose approvers.
     * Only the Select button differs: primary color instead of red, and full opacity
     * while disabled (it's disabled until at least one user is checked).
     */
    return class extends SelectRecordsModalView {

        className = 'dialog dialog-record ' + CLASS_NAME

        setup() {
            super.setup();

            const button = this.buttonList.find(item => item.name === 'select');

            if (button) {
                button.style = 'primary';
            }

            addStyle();
        }
    };

    function addStyle() {
        if (document.getElementById(CLASS_NAME + '-style')) {
            return;
        }

        const style = document.createElement('style');

        style.id = CLASS_NAME + '-style';
        style.textContent = `
            .${CLASS_NAME} .btn[data-name="select"].disabled,
            .${CLASS_NAME} .btn[data-name="select"][disabled] {
                opacity: 1;
            }
        `;

        document.head.appendChild(style);
    }
});

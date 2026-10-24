define('approvals:views/modals/resolve', ['views/modal'], (ModalView) => {

    return class extends ModalView {

        className = 'dialog'

        backdrop = true

        // language=Handlebars
        templateContent = `
            {{#if commentDisabled}}
                <p>{{confirmText}}</p>
            {{else}}
                <div class="margin-bottom">
                    <label class="control-label">{{translate 'Comment'}}</label>
                    <textarea class="form-control" data-name="comment" rows="4" maxlength="2000"></textarea>
                </div>
            {{/if}}
        `

        data() {
            const isApprove = this.options.action === 'approve';

            return {
                commentDisabled: !!this.options.commentDisabled,
                confirmText: this.translate(isApprove ? 'approvesConfirmApprove' : 'approvesConfirmDecline', 'messages'),
            };
        }

        setup() {
            const isApprove = this.options.action === 'approve';
            const label = this.translate(isApprove ? 'Approve' : 'Decline');

            this.headerText = this.options.recordName ? `${label}: ${this.options.recordName}` : label;

            this.buttonList = [
                {
                    name: 'resolve',
                    text: label,
                    style: isApprove ? 'success' : 'danger',
                    onClick: () => this.actionResolve(),
                },
                {
                    name: 'cancel',
                    label: 'Cancel',
                },
            ];
        }

        afterRender() {
            if (!this.options.commentDisabled) {
                setTimeout(() => this.$el.find('textarea').focus(), 50);
            }
        }

        async actionResolve() {
            const comment = this.options.commentDisabled ? null : (this.$el.find('textarea').val() || '');

            const url = 'Approves/' + encodeURIComponent(this.options.entityType) + '/' +
                encodeURIComponent(this.options.id) + '/' + encodeURIComponent(this.options.field) + '/resolve';

            this.disableButton('resolve');

            Espo.Ui.notifyWait();

            let response;

            try {
                response = await Espo.Ajax.postRequest(url, {
                    action: this.options.action,
                    comment: comment,
                });
            } catch (e) {
                this.enableButton('resolve');

                return;
            }

            Espo.Ui.success(this.translate('approvesDone', 'messages'));

            this.trigger('done', response);

            this.close();
        }
    };
});

define('approvals:views/stream/notes/approval', ['views/stream/note'], (NoteStreamView) => {

    /**
     * A Stream entry of the 'Approval' type: a decision, or a change to the approver list.
     */
    return class extends NoteStreamView {

        // language=Handlebars
        templateContent = `
            {{#unless noEdit}}
                <div class="pull-right right-container">{{{right}}}</div>
            {{/unless}}
            <div class="stream-head-container">
                <div class="pull-left">{{{avatar}}}</div>
                <div class="stream-head-text-container">
                    {{#if iconHtml}}{{{iconHtml}}}{{/if}}<span class="text-muted message">{{{message}}}</span>
                </div>
            </div>
            <div class="stream-post-container">
                {{#if isDecision}}
                    <div>
                        <span class="label label-{{style}}">{{statusLabel}}</span>
                        <span class="text-muted small">{{fieldLabel}}{{#if stageText}} · {{stageText}}{{/if}}</span>
                    </div>
                    {{#if comment}}
                        <div style="white-space: pre-wrap; margin-top: 4px;">{{comment}}</div>
                    {{/if}}
                {{else}}
                    <div class="text-muted small">{{fieldLabel}}</div>
                    {{#each changeList}}
                        <div style="margin-top: 2px;">
                            <span class="text-muted">{{label}}:</span> {{text}}
                        </div>
                    {{/each}}
                {{/if}}
            </div>
            <div class="stream-date-container">
                <a class="text-muted small" href="#Note/view/{{model.id}}">{{{createdAt}}}</a>
            </div>
        `

        init() {
            const data = this.model.get('data') || {};

            this.messageName = {
                approve: 'approvesApprove',
                decline: 'approvesDecline',
            }[data.action] || 'approvesUpdate';

            super.init();
        }

        setup() {
            this.createMessage();
        }

        data() {
            const data = this.model.get('data') || {};
            const parentType = this.model.get('parentType');
            const isDecision = data.action === 'approve' || data.action === 'decline';
            const status = data.action === 'approve' ? 'Approved' : 'Declined';
            const statusMap = this.getLanguage().translatePath(['Global', 'options', 'approvesStatus']) || {};

            const stageText = number => number ?
                this.translate('approvesStage', 'messages').replace('{number}', number) : null;

            const describe = item => item.name + (item.stage ? ' (' + stageText(item.stage) + ')' : '');

            const changeList = [];

            if ((data.added || []).length) {
                changeList.push({label: this.translate('Added'), text: data.added.map(describe).join(', ')});
            }

            if ((data.removed || []).length) {
                changeList.push({label: this.translate('Removed'), text: data.removed.map(describe).join(', ')});
            }

            if ((data.moved || []).length) {
                changeList.push({
                    label: this.translate('Moved'),
                    text: data.moved
                        .map(item => item.name + ': ' + stageText(item.from) + ' → ' + stageText(item.to))
                        .join(', '),
                });
            }

            return {
                ...super.data(),
                iconHtml: this.getIconHtml(),
                isDecision: isDecision,
                style: data.action === 'approve' ? 'success' : 'danger',
                statusLabel: statusMap[status] || status,
                stageText: stageText(data.stage),
                comment: data.comment || null,
                fieldLabel: data.field ? this.translate(data.field, 'fields', parentType) : '',
                changeList: changeList,
            };
        }
    };
});

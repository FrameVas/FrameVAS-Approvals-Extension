define('approvals:views/fields/approves', ['views/fields/base'], (BaseFieldView) => {

    // In list views, how many names to show before "+N more".
    const LIST_MAX_COUNT = 5;

    const STYLE_MAP = {
        Pending: 'warning',
        Approved: 'success',
        Declined: 'danger',
    };

    // Filter conditions. Each matches index tokens |{userId}:{status}:{A or W}| (see the backend Util).
    const SEARCH_TYPE_LIST = ['anyOf', 'allOf', 'noneOf', 'approvedBy', 'declinedBy', 'waitingFor'];

    // Replaced with the current user's ID when the filter is applied.
    const ME = '__me__';

    // The Approve and Decline buttons shown inside the field (positions 'top' and 'bottom').
    const ACTIONS_HTML = `
        <button type="button" class="btn btn-success btn-sm"
            data-action="approvesResolve" data-value="approve">{{translate 'Approve'}}</button>
        <button type="button" class="btn btn-default btn-sm"
            data-action="approvesResolve" data-value="decline">{{translate 'Decline'}}</button>
    `;

    const MODE_PARALLEL = 'parallel';
    const MODE_STAGES = 'stages';

    return class extends BaseFieldView {

        type = 'approves'

        // language=Handlebars
        listTemplateContent = `
            {{#if itemList.length}}
                {{#if showSummary}}
                    <div>
                        <span class="label label-{{summaryStyle}}">{{approvedCount}}/{{itemList.length}}</span>
                        <span class="text-muted">{{summaryText}}</span>
                    </div>
                {{/if}}
                {{#if showApprovers}}
                    <div class="approves-names"
                        style="display: flex; flex-wrap: wrap; align-items: center; gap: 3px;{{#if showSummary}} margin-top: 4px;{{/if}}">
                        {{#each listGroupList}}
                            {{#unless @first}}<span class="text-muted small">›</span>{{/unless}}
                            {{#if ../showStageNumbers}}<span class="text-muted small" title="{{title}}">{{number}}:</span>{{/if}}
                            {{#each items}}
                                <span class="label label-{{listStyle}}" title="{{tooltip}}"
                                    style="font-weight: normal;">{{name}}</span>
                            {{/each}}
                        {{/each}}
                        {{#if moreCount}}
                            <span class="text-muted small" title="{{moreTooltip}}">{{moreText}}</span>
                        {{/if}}
                    </div>
                {{/if}}
            {{/if}}
        `

        // language=Handlebars
        detailTemplateContent = `
            {{#if itemList.length}}
                {{#if canActTop}}
                    <div class="approves-actions" style="margin-bottom: 8px;">${ACTIONS_HTML}</div>
                {{/if}}
                <div class="approves-list">
                    {{#each groupList}}
                        {{#if ../isStages}}
                            <div class="approves-stage-header" style="margin-top: {{#if @first}}0{{else}}8px{{/if}};">
                                <span class="text-muted small" style="font-weight: 600;">{{title}}</span>
                                <span class="label label-{{stateStyle}}" style="font-weight: normal;">{{stateLabel}}</span>
                            </div>
                        {{/if}}
                        {{#each items}}
                            <div class="approves-item" style="padding: 4px 0;{{#if ../../isStages}} padding-left: 10px;{{/if}}">
                                <div>
                                    {{{avatarHtml}}}
                                    <a href="#User/view/{{id}}" data-id="{{id}}" data-scope="User">{{name}}</a>
                                    {{#if isWaiting}}
                                        <span class="label label-default">{{translate 'Waiting'}}</span>
                                    {{else}}
                                        <span class="label label-{{style}}">{{statusLabel}}</span>
                                    {{/if}}
                                    {{#if resolvedAtDisplay}}
                                        <span class="text-muted small">{{resolvedAtDisplay}}</span>
                                    {{/if}}
                                </div>
                                {{#if comment}}
                                    <div class="text-muted small" style="white-space: pre-wrap; padding-left: 20px;">{{comment}}</div>
                                {{/if}}
                            </div>
                        {{/each}}
                    {{/each}}
                </div>
                {{#if canActBottom}}
                    <div class="approves-actions" style="margin-top: 8px;">${ACTIONS_HTML}</div>
                {{/if}}
            {{else}}
                <span class="none-value">{{translate 'None'}}</span>
            {{/if}}
        `

        // language=Handlebars
        editTemplateContent = `
            {{#each groupList}}
                {{#if ../isStages}}
                    <div class="text-muted small" style="font-weight: 600; margin: {{#if @first}}0{{else}}10px{{/if}} 0 4px;">
                        {{title}}
                    </div>
                {{/if}}
                {{#if items.length}}
                    <div class="list-group approves-edit-list" style="margin-bottom: 6px;">
                        {{#each items}}
                            <div class="list-group-item" data-id="{{id}}">
                                <a role="button" tabindex="0" class="pull-right" data-action="approvesRemove"
                                    data-id="{{id}}" title="{{translate 'Remove'}}"><span class="fas fa-times"></span></a>
                                {{#if ../../isStages}}
                                    {{#if canMoveDown}}
                                        <a role="button" tabindex="0" class="pull-right" style="margin-right: 10px;"
                                            data-action="approvesMoveDown" data-id="{{id}}"
                                            title="{{../../moveDownTitle}}"><span class="fas fa-arrow-down"></span></a>
                                    {{/if}}
                                    {{#if canMoveUp}}
                                        <a role="button" tabindex="0" class="pull-right" style="margin-right: 10px;"
                                            data-action="approvesMoveUp" data-id="{{id}}"
                                            title="{{../../moveUpTitle}}"><span class="fas fa-arrow-up"></span></a>
                                    {{/if}}
                                {{/if}}
                                <span>{{name}}</span>
                                {{#unless isPending}}
                                    <span class="label label-{{style}}">{{statusLabel}}</span>
                                {{/unless}}
                            </div>
                        {{/each}}
                    </div>
                {{/if}}
                <button type="button" class="btn btn-default btn-sm" data-action="approvesAdd" data-stage="{{number}}">
                    <span class="fas fa-plus"></span> {{translate 'Add Approver'}}
                </button>
            {{/each}}
            {{#if isStages}}
                <div style="margin-top: 10px;">
                    <button type="button" class="btn btn-default btn-sm" data-action="approvesAddStage">
                        <span class="fas fa-layer-group"></span> {{translate 'Add Stage'}}
                    </button>
                </div>
            {{/if}}
        `

        // language=Handlebars
        searchTemplateContent = `
            <select class="form-control input-sm" data-name="approvesSearchType">
                {{#each searchTypeOptionList}}
                    <option value="{{value}}" {{#if selected}}selected{{/if}}>{{label}}</option>
                {{/each}}
            </select>
            {{#if searchItemList.length}}
                <div class="list-group" style="margin: 6px 0 0;">
                    {{#each searchItemList}}
                        <div class="list-group-item" style="padding: 4px 10px;">
                            <a role="button" tabindex="0" class="pull-right" data-action="approvesSearchRemove"
                                data-id="{{id}}" title="{{translate 'Remove'}}"><span class="fas fa-times"></span></a>
                            {{name}}
                        </div>
                    {{/each}}
                </div>
            {{/if}}
            <div style="margin-top: 6px;">
                <button type="button" class="btn btn-default btn-sm" data-action="approvesSearchAdd">
                    <span class="fas fa-plus"></span> {{translate 'Select'}}
                </button>
                {{#unless hasMe}}
                    <button type="button" class="btn btn-default btn-sm" data-action="approvesSearchMe">
                        <span class="fas fa-user"></span> {{translate 'Me'}}
                    </button>
                {{/unless}}
            </div>
        `

        setup() {
            super.setup();

            this.setupSearch();

            // The 'Sequential' option from versions before 1.3 isn't in the field's params any more.
            this.isLegacySequential = !!this.getMetadata()
                .get(['entityDefs', this.model.entityType, 'fields', this.name, 'sequential']);

            this.approvalMode = this.params.approvalMode || (this.isLegacySequential ? MODE_STAGES : MODE_PARALLEL);

            this.addActionHandler('approvesAdd', (e, target) => this.actionAdd(parseInt(target.dataset.stage) || 1));
            this.addActionHandler('approvesAddStage', () => this.addStage());
            this.addActionHandler('approvesRemove', (e, target) => this.removeItem(target.dataset.id));
            this.addActionHandler('approvesMoveUp', (e, target) => this.moveToStage(target.dataset.id, -1));
            this.addActionHandler('approvesMoveDown', (e, target) => this.moveToStage(target.dataset.id, 1));
            this.addActionHandler('approvesResolve', (e, target) => this.actionResolve(target.dataset.value));

            this.actionsPosition = this.params.actionsPosition || 'bottom';

            // The working copy used in edit mode.
            this.listenTo(this, 'mode-changed', () => this.copyFromModel());
            this.copyFromModel();
        }

        setupSearch() {
            const data = (this.searchParams && this.searchParams.data) || {};

            this.searchType = SEARCH_TYPE_LIST.includes(data.type) ? data.type : 'anyOf';
            this.searchIds = Array.isArray(data.idList) ? [...data.idList] : [];
            this.searchNames = {...(data.nameHash || {})};

            this.addActionHandler('approvesSearchAdd', () => this.actionSearchAdd());
            this.addActionHandler('approvesSearchMe', () => this.addSearchUser(ME, this.translate('Me')));
            this.addActionHandler('approvesSearchRemove', (e, target) => {
                this.saveSearchType();
                this.searchIds = this.searchIds.filter(id => id !== target.dataset.id);
                this.reRender();
            });
        }

        saveSearchType() {
            const select = this.element && this.element.querySelector('[data-name="approvesSearchType"]');

            if (select) {
                this.searchType = select.value;
            }
        }

        addSearchUser(id, name) {
            this.saveSearchType();

            if (!this.searchIds.includes(id)) {
                this.searchIds.push(id);
                this.searchNames[id] = name;
            }

            this.reRender();
        }

        async actionSearchAdd() {
            Espo.Ui.notifyWait();

            const view = await this.createView('modal', 'views/modals/select-records', {
                entityType: 'User',
                multiple: true,
                createButton: false,
                onSelect: models => models.forEach(model => this.addSearchUser(model.id, model.get('name'))),
            });

            await view.render();

            Espo.Ui.notify(false);
        }

        getSearchData() {
            const typeLabels = this.getLanguage().translatePath(['Global', 'options', 'approvesSearchType']) || {};

            return {
                searchTypeOptionList: SEARCH_TYPE_LIST.map(value => ({
                    value: value,
                    label: typeLabels[value] || value,
                    selected: value === this.searchType,
                })),
                searchItemList: this.searchIds.map(id => ({
                    id: id,
                    name: id === ME ? this.translate('Me') : (this.searchNames[id] || id),
                })),
                hasMe: this.searchIds.includes(ME),
            };
        }

        /**
         * Builds the search condition. It matches the hidden index attribute with LIKE.
         */
        fetchSearch() {
            this.saveSearchType();

            const type = this.searchType;
            const attribute = this.name + 'Index';

            const data = {
                type: type,
                idList: [...this.searchIds],
                nameHash: {...this.searchNames},
            };

            if (!this.searchIds.length) {
                return {type: 'isNotNull', attribute: 'id', data: data};
            }

            const ids = this.searchIds.map(id => id === ME ? this.getUser().id : id);

            const pattern = id => ({
                anyOf: `%|${id}:%`,
                allOf: `%|${id}:%`,
                noneOf: `%|${id}:%`,
                approvedBy: `%|${id}:Approved:%`,
                declinedBy: `%|${id}:Declined:%`,
                waitingFor: `%|${id}:Pending:A|%`,
            }[type]);

            if (type === 'noneOf') {
                return {
                    type: 'and',
                    value: ids.map(id => ({
                        type: 'or',
                        value: [
                            {type: 'notLike', attribute: attribute, value: pattern(id)},
                            {type: 'isNull', attribute: attribute},
                        ],
                    })),
                    data: data,
                };
            }

            return {
                type: type === 'allOf' ? 'and' : 'or',
                value: ids.map(id => ({type: 'like', attribute: attribute, value: pattern(id)})),
                data: data,
            };
        }

        /**
         * The record view (detail or edit form) containing this field, if any.
         */
        getRecordView() {
            let view = this.getParentView();

            for (let i = 0; view && i < 8; i++) {
                if (typeof view.setEditMode === 'function' && typeof view.setDetailMode === 'function') {
                    return view;
                }

                view = view.getParentView();
            }

            return null;
        }

        isRecordInEditMode() {
            const recordView = this.getRecordView();

            return !!recordView && recordView.mode === 'edit';
        }

        /**
         * Whether the current user can approve or decline right now.
         * Not while the record form is in edit mode: a decision reloads the record,
         * which would discard unsaved changes.
         */
        canAct(activeIds) {
            return this.isDetailMode() && !this.isRecordInEditMode() &&
                activeIds.includes(this.getUser().id);
        }

        getHeaderButtonName(action) {
            return 'approves' + (action === 'approve' ? 'Approve' : 'Decline') + '_' + this.name;
        }

        /**
         * Position 'header': the buttons are in the record's header, next to Edit.
         * They are added once and then shown or hidden to follow the approval state.
         */
        updateHeaderButtons(recordView) {
            if (this.actionsPosition !== 'header' || !recordView || typeof recordView.addButton !== 'function') {
                return;
            }

            if (!this.headerButtonsAdded) {
                this.headerButtonsAdded = true;

                const label = this.getLabelText();

                recordView.addButton({
                    name: this.getHeaderButtonName('approve'),
                    text: this.translate('Approve'),
                    title: label,
                    style: 'success',
                    hidden: true,
                    onClick: () => this.actionResolve('approve'),
                });

                recordView.addButton({
                    name: this.getHeaderButtonName('decline'),
                    text: this.translate('Decline'),
                    title: label,
                    hidden: true,
                    onClick: () => this.actionResolve('decline'),
                });

                this.once('remove', () => {
                    recordView.removeActionItem(this.getHeaderButtonName('approve'));
                    recordView.removeActionItem(this.getHeaderButtonName('decline'));
                });
            }

            const canAct = this.canAct(this.getActiveIds(this.items || []));

            for (const action of ['approve', 'decline']) {
                canAct ?
                    recordView.showActionItem(this.getHeaderButtonName(action)) :
                    recordView.hideActionItem(this.getHeaderButtonName(action));
            }
        }

        afterRender() {
            super.afterRender();

            const recordView = this.getRecordView();

            if (this.isDetailMode()) {
                this.updateHeaderButtons(recordView);
            }

            // A read-only field stays in view mode while the form is edited; show or hide the buttons.
            if (recordView && !this.recordModeListened) {
                this.recordModeListened = true;

                this.listenTo(recordView, 'after:mode-change', () => {
                    if (this.isDetailMode() && this.isRendered()) {
                        this.reRender();
                    }
                });
            }
        }

        getAttributeList() {
            return [this.name, this.name + 'Status'];
        }

        isStages() {
            return this.approvalMode === MODE_STAGES;
        }

        copyFromModel() {
            const raw = Espo.Utils.cloneDeep(this.model.get(this.name) || []);

            // Items saved before stages existed have no stage number (mirrors the backend logic).
            this.items = raw.map((item, i) => ({
                ...item,
                stage: item.stage >= 1 ? item.stage : (this.isLegacySequential ? i + 1 : 1),
            }));

            this.stageCount = Math.max(1, ...this.items.map(item => item.stage));
        }

        getStatusTranslation(status) {
            const map = this.getLanguage().translatePath(['Global', 'options', 'approvesStatus']) || {};

            return map[status] || status;
        }

        getStageTitle(number) {
            return this.translate('approvesStage', 'messages').replace('{number}', number);
        }

        /**
         * Overall status (mirrors the backend logic).
         */
        computeStatus(items) {
            if (!items.length) {
                return '';
            }

            if (items.some(item => item.status === 'Declined')) {
                return 'Declined';
            }

            return items.some(item => item.status === 'Pending') ? 'Pending' : 'Approved';
        }

        /**
         * The first stage with pending approvers, or null when nobody can act.
         */
        getCurrentStage(items) {
            if (this.computeStatus(items) !== 'Pending') {
                return null;
            }

            return Math.min(...items.filter(item => item.status === 'Pending').map(item => item.stage));
        }

        /**
         * Users who can approve or decline right now (mirrors the backend logic).
         */
        getActiveIds(items) {
            if (this.computeStatus(items) !== 'Pending') {
                return [];
            }

            const currentStage = this.getCurrentStage(items);

            return items
                .filter(item => item.status === 'Pending')
                .filter(item => !this.isStages() || item.stage === currentStage)
                .map(item => item.id);
        }

        data() {
            if (this.isSearchMode()) {
                return {...super.data(), ...this.getSearchData()};
            }

            if (!this.isEditMode()) {
                this.copyFromModel();
            }

            const isStages = this.isStages();
            const items = this.items || [];
            const activeIds = this.getActiveIds(items);
            const status = this.model.get(this.name + 'Status') || this.computeStatus(items);
            const currentStage = isStages ? this.getCurrentStage(items) : null;

            const itemList = items.map(item => {
                const isWaiting = isStages && item.status === 'Pending' && status === 'Pending' &&
                    !activeIds.includes(item.id);

                const statusLabel = this.getStatusTranslation(item.status);
                const resolvedAtDisplay = item.resolvedAt ? this.getDateTime().toDisplay(item.resolvedAt) : null;

                return {
                    id: item.id,
                    name: item.name || item.id,
                    stage: item.stage,
                    isPending: item.status === 'Pending',
                    isWaiting: isWaiting,
                    style: STYLE_MAP[item.status] || 'default',
                    listStyle: isWaiting ? 'default' : (STYLE_MAP[item.status] || 'default'),
                    statusLabel: statusLabel,
                    resolvedAtDisplay: resolvedAtDisplay,
                    comment: item.comment || null,
                    tooltip: [
                        isStages ? this.getStageTitle(item.stage) : null,
                        isWaiting ? this.translate('Waiting') : statusLabel,
                        resolvedAtDisplay,
                        item.comment,
                    ].filter(part => part).join(' · '),
                    canMoveUp: item.stage > 1,
                    canMoveDown: item.stage < this.stageCount ||
                        items.filter(other => other.stage === item.stage).length > 1,
                    avatarHtml: this.isDetailMode() ?
                        this.getHelper().getAvatarHtml(item.id, 'small', 16, 'avatar-link') : '',
                };
            });

            const groupList = this.buildGroups(itemList, currentStage, this.isEditMode() ? this.stageCount : 0);

            const listDisplay = this.params.listDisplay || 'summary';
            const moreList = itemList.slice(LIST_MAX_COUNT);

            let summaryText = status ? this.getStatusTranslation(status) : '';

            if (isStages && currentStage && groupList.length > 1) {
                summaryText += ' · ' + this.getStageTitle(currentStage + '/' + groupList.length);
            }

            return {
                ...super.data(),
                itemList: itemList,
                isStages: isStages,
                showStageNumbers: isStages && new Set(itemList.map(item => item.stage)).size > 1,
                groupList: groupList,
                // Hidden while the record form is in edit mode: a decision reloads the record,
                // which would discard unsaved changes.
                canActTop: this.canAct(activeIds) && this.actionsPosition === 'top',
                canActBottom: this.canAct(activeIds) && this.actionsPosition === 'bottom',
                approvedCount: items.filter(item => item.status === 'Approved').length,
                summaryStyle: STYLE_MAP[status] || 'default',
                summaryText: summaryText,
                showSummary: listDisplay !== 'approvers',
                showApprovers: listDisplay !== 'summary',
                listGroupList: this.buildGroups(itemList.slice(0, LIST_MAX_COUNT), currentStage, 0),
                moreCount: moreList.length,
                moreText: this.translate('approvesMore', 'messages').replace('{count}', moreList.length),
                moreTooltip: moreList.map(item => item.name + ' (' + item.tooltip + ')').join('\n'),
                moveUpTitle: this.translate('approvesMoveToPreviousStage', 'messages'),
                moveDownTitle: this.translate('approvesMoveToNextStage', 'messages'),
            };
        }

        /**
         * Groups items by stage. In parallel mode there is one group.
         * minCount keeps empty stages visible (in edit mode, after 'Add Stage').
         */
        buildGroups(itemList, currentStage, minCount) {
            if (!this.isStages()) {
                return [{number: 1, items: itemList}];
            }

            const count = Math.max(minCount, ...itemList.map(item => item.stage), 0);
            const groups = [];

            for (let number = 1; number <= count; number++) {
                const groupItems = itemList.filter(item => item.stage === number);

                if (!groupItems.length && !minCount) {
                    continue;
                }

                let state = 'Waiting';
                let stateStyle = 'default';

                if (groupItems.some(item => item.style === 'danger')) {
                    state = 'Declined';
                    stateStyle = 'danger';
                } else if (groupItems.length && groupItems.every(item => item.style === 'success')) {
                    state = 'Done';
                    stateStyle = 'success';
                } else if (number === currentStage) {
                    state = 'Current';
                    stateStyle = 'warning';
                }

                groups.push({
                    number: number,
                    title: this.getStageTitle(number),
                    items: groupItems,
                    stateLabel: state === 'Declined' ? this.getStatusTranslation('Declined') : this.translate(state),
                    stateStyle: stateStyle,
                });
            }

            return groups;
        }

        async actionAdd(stage) {
            Espo.Ui.notifyWait();

            const view = await this.createView('modal', 'views/modals/select-records', {
                entityType: 'User',
                multiple: true,
                createButton: false,
                primaryFilterName: 'active',
                onSelect: models => {
                    models.forEach(model => {
                        if (this.items.some(item => item.id === model.id)) {
                            return;
                        }

                        this.items.push({
                            id: model.id,
                            name: model.get('name'),
                            stage: this.isStages() ? stage : 1,
                            status: 'Pending',
                            resolvedAt: null,
                            comment: null,
                        });
                    });

                    this.onItemsChanged();
                },
            });

            await view.render();

            Espo.Ui.notify(false);
        }

        addStage() {
            this.stageCount++;

            this.reRender();
        }

        removeItem(id) {
            this.items = this.items.filter(item => item.id !== id);

            this.onItemsChanged();
        }

        moveToStage(id, step) {
            const item = this.items.find(item => item.id === id);

            if (!item) {
                return;
            }

            item.stage = Math.max(1, item.stage + step);
            this.stageCount = Math.max(this.stageCount, item.stage);

            this.onItemsChanged();
        }

        async onItemsChanged() {
            this.trigger('change');

            await this.reRender();
        }

        /**
         * Stage numbers without gaps (empty stages are dropped), items sorted by stage.
         */
        getCompactedItems() {
            const items = Espo.Utils.cloneDeep(this.items || []);

            if (!this.isStages()) {
                return items.map(item => ({...item, stage: 1}));
            }

            const numbers = [...new Set(items.map(item => item.stage))].sort((a, b) => a - b);

            return items
                .map((item, i) => ({...item, stage: numbers.indexOf(item.stage) + 1, position: i}))
                .sort((a, b) => a.stage - b.stage || a.position - b.position)
                .map(({position, ...item}) => item);
        }

        fetch() {
            return {
                [this.name]: this.getCompactedItems(),
            };
        }

        validateRequired() {
            if (!this.isRequired()) {
                return false;
            }

            if ((this.items || []).length) {
                return false;
            }

            const msg = this.translate('fieldIsRequired', 'messages').replace('{field}', this.getLabelText());

            this.showValidationMessage(msg, '[data-action="approvesAdd"]');

            return true;
        }

        async actionResolve(action) {
            const view = await this.createView('dialog', 'approvals:views/modals/resolve', {
                action: action,
                entityType: this.model.entityType,
                id: this.model.id,
                field: this.name,
                recordName: this.model.get('name'),
                commentDisabled: !!this.params[action + 'CommentDisabled'],
            });

            this.listenToOnce(view, 'done', response => {
                // Show the decision right away; the field re-renders automatically.
                this.model.set(response);

                // Reload the record, like after a normal save. This refreshes the other fields
                // (e.g. Modified At, or ones changed by formula) and the Stream panel.
                this.model.fetch();
            });

            await view.render();
        }
    };
});

/* EGO_PROJECT_WORKFLOW_360_V2 */
(function () {
    function initWorkflow360() {
        const flow = document.querySelector('[data-workflow-360]');
        const current = flow?.querySelector('.pt-flow__step.is-current');
        if (flow && current) {
            const target = Math.max(0, current.offsetLeft - (flow.clientWidth - current.clientWidth) / 2);
            flow.scrollTo({ left: target, behavior: 'auto' });
        }

        document.querySelectorAll('[data-workflow-stage]').forEach((stage) => {
            stage.addEventListener('click', () => {
                const tabName = stage.dataset.targetTab;
                if (!tabName) return;
                document.querySelector(`[data-pt-tab="${tabName}"]`)?.click();
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initWorkflow360);
    } else {
        initWorkflow360();
    }
})();

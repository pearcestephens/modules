<?php
/**
 * Consignments Template (Unified CIS Wrapper)
 * Provides startContent/endContent legacy API + optional UX layer.
 */
require_once __DIR__ . '/../base/bootstrap.php';
require_once __DIR__ . '/lib/CISTemplate.php';

class ConsignmentsTemplate {
    private CISTemplate $tpl;
    private bool $contentStarted = false;
    private bool $uxEnabled = true;
    private ?string $currentPage = null; // retained for compatibility

    public function __construct(array $options = []) {
        $this->tpl = new CISTemplate();
        $this->setTitle($options['title'] ?? 'Consignments - The Vape Shed CIS');
        if (isset($options['uxEnabled'])) { $this->uxEnabled = (bool)$options['uxEnabled']; }
    }

    public function setTitle(string $title): self { $this->tpl->setTitle($title); return $this; }
    public function setCurrentPage(string $page): self { $this->currentPage = $page; return $this; }
    public function addCSS(string $href): self { $this->tpl->addHeadCSS($href); return $this; }
    public function addJS(string $src, bool $defer=false): self { $this->tpl->addHeadJS($src,$defer); return $this; }
    public function addInlineCSS(string $css): self { $this->tpl->addInlineHead($css,'css'); return $this; }
    public function addInlineJS(string $js): self { $this->tpl->addInlineHead($js,'js'); return $this; }

    public function startContent(): void {
        if ($this->contentStarted) return;
        $this->contentStarted = true;
        if ($this->uxEnabled) { $this->injectModuleStyling(); }
        $this->tpl->startContent();
        echo '<div class="container-fluid">' . PHP_EOL;
        echo '  <div class="animated fadeIn" data-consignments-wrapper="1">' . PHP_EOL;
    }

    public function endContent(): void {
        if (!$this->contentStarted) return;
        echo '  </div><!-- .animated -->' . PHP_EOL;
        echo '</div><!-- .container-fluid -->' . PHP_EOL;
        if ($this->uxEnabled) { $this->injectModuleFooterJS(); }
        $this->tpl->endContent();
        $this->tpl->render(); // legacy: render at endContent
    }

    public function renderPage(string $content, array $options=[]): void {
        if (isset($options['title'])) { $this->setTitle($options['title']); }
        if (isset($options['currentPage'])) { $this->setCurrentPage($options['currentPage']); }
        if (isset($options['uxEnabled'])) { $this->uxEnabled = (bool)$options['uxEnabled']; }
        $this->startContent();
        echo $content;
        $this->endContent();
    }

    private function injectModuleStyling(): void {
        $css = <<<'CSS'
/* Consignments UX Layer */
.consignment-card{border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,.1);margin-bottom:1.5rem;transition:transform .2s,box-shadow .2s}
.consignment-card:hover{transform:translateY(-2px);box-shadow:0 4px 8px rgba(0,0,0,.15)}
.status-badge{padding:.35rem .75rem;border-radius:20px;font-size:.85rem;font-weight:600;text-transform:uppercase}
.status-draft{background:#6c757d;color:#fff}
.status-pending{background:#ffc107;color:#212529}
.status-sent{background:#17a2b8;color:#fff}
.status-received{background:#28a745;color:#fff}
.status-cancelled{background:#dc3545;color:#fff}
.ai-badge{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;padding:.25rem .6rem;border-radius:12px;font-size:.75rem;font-weight:600;display:inline-flex;align-items:center;gap:.3rem}
.ai-button{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);border:none;color:#fff;padding:.5rem 1rem;border-radius:6px;font-weight:600;transition:all .2s;cursor:pointer}
.ai-button:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(102,126,234,.4)}
.anomaly-alert{border-left:4px solid #dc3545;background:#fff5f5;padding:1rem;margin-bottom:1rem;border-radius:4px}
.anomaly-warning{border-left:4px solid #ffc107;background:#fffbf0;padding:1rem;margin-bottom:1rem;border-radius:4px}
.anomaly-info{border-left:4px solid #17a2b8;background:#f0f9ff;padding:1rem;margin-bottom:1rem;border-radius:4px}
CSS;
        $this->addInlineCSS($css);
    }

    private function injectModuleFooterJS(): void {
        $js = <<<'JS'
// Consignments JS Namespace
(function(){'use strict';window.CIS=window.CIS||{};window.CIS.Consignments={
version:'1.0.0',ajax:(u,o)=>CIS.Core.ajax(u,o),get:(u,p)=>CIS.Core.get(u,p),post:(u,d)=>CIS.Core.post(u,d),
formatCurrency:a=>CIS.Core.formatCurrency(a),formatDate:d=>CIS.Core.formatDate(d),formatDateTime:d=>CIS.Core.formatDateTime(d),
toast:(m,t,x)=>CIS.Core.toast(m,t,x),confirm:(m,c,cc)=>CIS.Core.confirm(m,c,cc),showLoading:m=>CIS.Core.showLoading(m),hideLoading:()=>CIS.Core.hideLoading(),
store:(k,v)=>CIS.Core.store(k,v,'cis_consignments_'),retrieve:(k,def)=>CIS.Core.retrieve(k,def,'cis_consignments_'),forget:k=>CIS.Core.forget(k,'cis_consignments_'),
logger:CIS.Core.createLogger('Consignments'),getStatusColor:s=>({draft:'#6c757d',pending:'#ffc107',sent:'#17a2b8',received:'#28a745',cancelled:'#dc3545'}[s]||'#6c757d'),
getStatusBadge:s=>`<span class="status-badge status-${s}">${s}</span>`,
askAI:(q,c={})=>CIS.Core.post('api/ai-assistant.php?action=ask',{question:q,context:c}),
getCarrierRecommendation:t=>CIS.Core.post('api/ai-assistant.php?action=recommend-carrier',{transfer:t}),
analyzeTransfer:id=>CIS.Core.post('api/ai-assistant.php?action=analyze-transfer',{consignment_id:id})};
window.ConsignmentsApp=window.CIS.Consignments;CIS.Consignments.logger.info('Consignments module ready');
if(CIS.Core.getConfig('debug')){console.group('Consignments Features');['AI Assistant','Carrier recommendations','Transfer analysis','Natural language Q&A','Cost predictions','CoreUI inheritance'].forEach(f=>console.log('✓ '+f));console.groupEnd();}
})();
JS;
        $this->addInlineJS($js);
    }
}

<?php
/** Beauty presentation owns only Beauty records; supplement renderers stay with their existing plugins. */
defined('ABSPATH') || exit;
function qby_knowledge_value($details,$key,$ar) {
    // INCI is a canonical label string. Prose must be in the requested language, never silently mixed.
    $suffix=in_array($key,array('directions','warnings','benefits'),true)?($ar?'_ar':'_en'):($ar?'_ar':'');
    return trim((string)($details[$key.$suffix]??''));
}
function qby_knowledge_render($id) {
    if(!qby_uses_beauty_ui($id)){return '';}
    $d=qby_details($id);$ar=qby_ar();$department=qby_product_department($id);$schema=qby_beauty_field_schema($department);$tool=$department==='beauty-tools';
    $product=wc_get_product($id);$variable=$product && $product->is_type('variable');
    $facts=array(qby_t('Product type','نوع المنتج')=>qby_product_type_label($id));
    if(!empty($d['size'])){$facts[qby_t('Size','الحجم')]=$d['size'];}
    foreach(array('skin_type','hair_type','skin_concern','hair_concern','body_concern','finish','coverage','undertone','texture','shade_notes','spf','concentration','fragrance_family','top_notes','heart_notes','base_notes','material','dimensions') as $key){
        $value=qby_knowledge_value($d,$key,$ar);if($key==='spf'){$value=trim((string)($d['spf']??''));}
        if($value && isset($schema[$key])){$facts[qby_t($schema[$key]['en'],$schema[$key]['ar'])]=$value;}
    }
    $benefits=qby_knowledge_value($d,'benefits',$ar);$use=qby_knowledge_value($d,'directions',$ar);$warnings=qby_knowledge_value($d,'warnings',$ar);$key_ingredients=qby_knowledge_value($d,'key_ingredients',$ar);$care=qby_knowledge_value($d,'tool_care',$ar);
    $choice=array(
      'makeup'=>array('Choose your shade, coverage and finish. Screen colours may differ from the product.','اختاري الدرجة والتغطية واللمسة النهائية. قد تختلف ألوان الشاشة عن المنتج.'),
      'skin-care'=>array('Explore the texture and full ingredients, then follow the directions on your exact product.','اكتشفي القوام والمكونات الكاملة، ثم اتبعي إرشادات منتجك المحدد.'),
      'hair-care'=>array('Find the right step: cleanse, condition or leave-in care. Each product has its own directions.','حددي خطوة العناية: التنظيف أو الترطيب أو العناية دون شطف. لكل منتج إرشاداته.'),
      'body-care'=>array('Check the intended area and texture, and follow the product’s directions.','راجعي منطقة الاستخدام والقوام، واتبعي إرشادات المنتج.'),
      'fragrance'=>array('Explore the concentration and fragrance notes. The experience of a scent is personal.','اكتشفي التركيز والنوتات العطرية. تجربة الرائحة تختلف من شخص لآخر.'),
      'beauty-tools'=>array('Check the material, dimensions and care instructions for your exact tool.','راجعي الخامة والأبعاد وإرشادات العناية بأداتك المحددة.'),
    );
    ob_start(); ?>
    <section class="qbx qbx-knowledge" id="qby-product-details" dir="<?php echo $ar?'rtl':'ltr'; ?>" aria-labelledby="qbx-knowledge-title">
      <header class="qbx-knowledge-heading"><div><span class="qbx-kicker"><?php echo esc_html(qby_t('QIMIA BEAUTY INTELLIGENCE','ذكاء كيميا للجمال')); ?></span><h2 id="qbx-knowledge-title"><?php echo esc_html(qby_t('Every detail. Made clear.','كل التفاصيل. بوضوح.')); ?></h2></div><span class="qbx-knowledge-category"><?php echo esc_html(qby_label($department?:'beauty')); ?></span></header>
      <?php if($variable): ?><p class="qbx-variant-note"><?php echo esc_html(qby_t('Select your exact shade and size in the product options. These are the parent product’s recorded details; individual shades and sets may have different formulas.','اختاري الدرجة والحجم من خيارات المنتج. هذه التفاصيل مسجلة للمنتج الأساسي؛ وقد تختلف تركيبات الدرجات والمجموعات.')); ?></p><?php endif; ?>
      <div class="qbx-knowledge-grid">
        <article class="qbx-facts" aria-labelledby="qbx-facts-title"><div class="qbx-knowledge-label"><span>01</span><span><?php echo esc_html(qby_t('THE PRODUCT, IN DETAIL','المنتج بالتفصيل')); ?></span></div><h3 id="qbx-facts-title"><?php echo esc_html(qby_t($tool?'Tool details':'Beauty facts',$tool?'تفاصيل الأداة':'مواصفات الجمال')); ?></h3>
          <dl><?php foreach($facts as $label=>$value): ?><div><dt><?php echo esc_html($label); ?></dt><dd><bdi><?php echo esc_html($value); ?></bdi></dd></div><?php endforeach; ?></dl>
          <?php if($key_ingredients): ?><div class="qbx-key-ingredients"><h4><?php echo esc_html(qby_t('Key ingredients','المكونات الرئيسية')); ?></h4><p><?php echo esc_html($key_ingredients); ?></p></div><?php endif; ?>
          <?php if(!$tool): ?><details class="qbx-ingredient-list"><summary><?php echo esc_html(qby_t('Full ingredients · INCI','المكونات الكاملة · INCI')); ?><span aria-hidden="true">+</span></summary><?php if(!empty($d['inci'])): ?><p class="qbx-inci" lang="en" dir="ltr"><?php echo esc_html($d['inci']); ?></p><?php else: ?><p><?php echo esc_html(qby_t('The complete ingredient list has not been recorded yet. Refer to the label of your exact product and shade.','لم تُسجّل قائمة المكونات الكاملة بعد. راجعي ملصق المنتج والدرجة المحددة.')); ?></p><?php endif; ?><p class="qbx-label-note"><?php echo esc_html(qby_t('Formulas may vary by shade, batch and market. Your package label is the reference.','قد تختلف التركيبة حسب الدرجة والدفعة والسوق. ملصق العبوة هو المرجع.')); ?></p></details><?php endif; ?>
          <?php if(!empty($d['source_url'])): ?><a class="qbx-text-link qbx-source" href="<?php echo esc_url($d['source_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html(qby_t('Product information source','مصدر معلومات المنتج')); ?><span aria-hidden="true">↗</span></a><?php endif; ?>
        </article>
        <article class="qbx-guide" aria-labelledby="qbx-guide-title"><div class="qbx-knowledge-label"><span>02</span><span><?php echo esc_html(qby_t('QIMIA INTELLIGENCE','ذكاء كيميا')); ?></span><i aria-hidden="true">✦</i></div><h3 id="qbx-guide-title"><?php echo esc_html(qby_t('Your Qimia guide','دليلك من كيميا')); ?></h3><p class="qbx-guide-intro"><?php echo esc_html(qby_t('From the details to your daily ritual.','من تفاصيل المنتج إلى روتينك اليومي.')); ?></p>
          <?php if($benefits): ?><div class="qbx-benefits"><h4><?php echo esc_html(qby_t('What to expect','ما الذي يقدمه لك')); ?></h4><ul><?php foreach(array_slice(preg_split('/\R/u',$benefits,-1,PREG_SPLIT_NO_EMPTY),0,4) as $benefit){echo '<li>'.esc_html($benefit).'</li>';} ?></ul></div><?php endif; ?>
          <details open><summary><?php echo esc_html(qby_t('How to use','طريقة الاستخدام')); ?><span aria-hidden="true">+</span></summary><div class="qbx-use-copy"><?php if($use){$steps=preg_split('/\R/u',$use,-1,PREG_SPLIT_NO_EMPTY);if(count($steps)>1){echo '<ol>';foreach($steps as $step){echo '<li>'.esc_html($step).'</li>';}echo '</ol>';}else{echo '<p>'.esc_html($use).'</p>';}}else{echo '<p>'.esc_html(qby_t('Follow the directions on this product’s packaging. Detailed instructions will appear here when verified.','اتبعي الإرشادات على عبوة هذا المنتج. ستظهر التعليمات التفصيلية هنا بعد التحقق منها.')).'</p>';} ?></div></details>
          <details><summary><?php echo esc_html(qby_t('Care & precautions','العناية والاحتياطات')); ?><span aria-hidden="true">+</span></summary><p><?php echo esc_html($warnings?:qby_t('Follow the precautions and storage instructions on your exact package.','اتبعي احتياطات وإرشادات التخزين على العبوة المحددة.')); ?></p><?php if($care){echo '<p>'.esc_html($care).'</p>';}if(!$tool){echo '<p>'.esc_html(!empty($d['pao'])?qby_t('Period after opening: ','مدة الاستخدام بعد الفتح: ').$d['pao']:qby_t('Check the open-jar symbol for the period after opening. This is separate from the expiry date.','راجعي رمز العبوة المفتوحة لمعرفة مدة الاستخدام بعد الفتح، وهي تختلف عن تاريخ انتهاء الصلاحية.')).'</p>';} ?></details>
          <?php if(isset($choice[$department])): ?><div class="qbx-choice"><h4><?php echo esc_html(qby_t('Before you choose','قبل أن تختاري')); ?></h4><p><?php echo esc_html(qby_t($choice[$department][0],$choice[$department][1])); ?></p></div><?php endif; ?>
          <button type="button" class="qbx-button qbx-guide-ask" data-qimia-ai-open data-qby-product-ask="<?php echo (int)$id; ?>"><?php echo esc_html(qby_t('Ask Qimia about this product','اسألي كيميا عن هذا المنتج')); ?><span aria-hidden="true">↗</span></button>
        </article>
      </div>
      <footer class="qbx-knowledge-footer"><?php echo qby_link(qby_browse_url($department?:'beauty'),qby_t('Explore this department ↗','اكتشفي هذا القسم ↗'),'qbx-text-link');echo qby_link(qby_url('/refund_policy/'),qby_t('Returns & refunds','الإرجاع والاسترداد'),'qbx-text-link'); ?></footer>
      <?php if(function_exists('qby_qil_records')){echo '<script type="application/json" class="qby-qil-records">'.wp_json_encode(qby_qil_records(array($id)),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script>';} ?>
    </section>
    <?php return ob_get_clean();
}

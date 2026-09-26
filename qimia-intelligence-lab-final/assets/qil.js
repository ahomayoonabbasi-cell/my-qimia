(() => {
	'use strict';

	const root = document.querySelector('#qimia-lab, [data-qil-shell]');
	if (!root) return;
	// Unique-ID shells share document-level lookups: header, product tools and dialogs.
	const shells = () => [...document.querySelectorAll('.qil-shell, [data-qil-shell]')];
	const delegate = document;

	const config = window.QIMIA_LAB || {};
	const useNativeWoodmartCart = config.woodmartCart === true;
	const products = Array.isArray(config.products) ? config.products : [];
	const productIndex = new Map(products.map(product => [String(product?.id || ''), product]).filter(([id]) => id));
	const remoteSearchRanks = new Map();
	let searchTimer = 0, searchController = null, searchFailureShown = false;
	let searchRequestSerial = 0, searchPendingKey = '', searchEventSignature = '';
	// Live search asks the server only from three characters, after a pause in
	// typing, and never twice for the same words during this page view.
	const SEARCH_REMOTE_MIN = Math.max(2, Math.min(4, Number(config.searchMinChars) || 3));
	const SEARCH_DEBOUNCE_MS = Math.max(200, Math.min(800, Number(config.searchDebounceMs) || 350));
	const searchResponses = new Map();
	const quickViewCache = new Map();
	const quickViewPrefetches = new Map();
	let quickViewController = null, variationController = null, variationTimer = 0, activeQuickView = null;
	const aiVisibilityState = new WeakMap();
	const aiOffsetState = new WeakMap();
	const aiNativePositioned = new WeakSet();
	const pageLanguage = String(root.dataset.qilLocale || config.locale || document.documentElement.lang || 'en').toLowerCase();
	const state = {
		lang: pageLanguage.startsWith('ar') ? 'ar' : 'en',
		goal: 'muscle', prefs: new Set(), search: '', visible: 8, compare: [],
		lastFocus: null, cartCount: null, cartHydrated: false, lastCartEvent: 0, searchPending: false
	};

	const copy = {
		en: {
			delivery: config.deliveryMessage || 'GCC delivery options shown at checkout', stage: 'Private staging · Cart preview on · Checkout off', menu: 'Shop', navShop: 'Shop', navGoals: 'Goals', navLab: 'Qimia AI', navCompare: 'Compare',
			askQimia: 'Ask Qimia AI', searchPlaceholder: 'Search a product, ingredient or goal…', eyebrow: 'Qimia Intelligence Lab · Live store data', heroOne: 'Your smartest route to', heroTwo: 'the right supplement.', heroLead: 'Live products, verified label facts and Qimia AI—one clear path from goal to cart.', buildWithAI: 'Build my routine with AI', live: 'LIVE', findMatch: 'Explore by goal', heroNote: 'Browsing context stays in this tab; messages you send are processed by Qimia AI', supplements: 'supplements', brands: 'trusted brands', muscatStore: 'store in Muscat', goalLabel: 'Explore by goal', storeData: 'store data', liveCatalogue: 'Qimia AI is ready', availabilityChecked: 'Ask about any live product', ingredients: 'Ingredients', servingsLabel: 'Servings', valueLabel: 'Value', scrollDiscover: 'Scroll to explore the digital store',
			startOutcome: 'START WITH YOUR OUTCOME', whatGoal: 'What would you like to support?', chooseGoal: 'Choose one goal. You can refine the shortlist afterward.', privateSession: 'Browsing context stays in this tab; messages you send are processed by Qimia AI', muscle: 'Build muscle', muscleSub: 'Protein · strength · mass', fatLoss: 'Fat loss', fatLossSub: 'Cut support · value · availability', performance: 'Performance', performanceSub: 'Pre-workout · creatine · amino', energy: 'Energy & focus', energySub: 'Drive · clarity · performance', recovery: 'Recovery', recoverySub: 'Hydration · repair · mobility', sleep: 'Sleep & calm', sleepSub: 'Rest · routine · balance', wellness: 'Daily wellness', wellnessSub: 'Vitamins · gut · immunity', beauty: 'Hair, skin & beauty', beautySub: 'Collagen · biotin · glow', refine: 'Verified filters:', stimFree: 'Stimulant-free', vegan: 'Vegan-friendly', bestValue: 'Value per serving', liveMatches: 'LIVE CATALOGUE MATCHES', selectedFor: 'Selected for', rankingNote: 'Ranked by verified relevance, product detail and availability—not discount size.', noMatches: 'No verified close match yet', noMatchesSub: 'Try another goal, clear the search or remove a strict filter.', showMore: 'Show more products',
			aiConnected: 'QIMIA AI · CONNECTED TO THE LIVE STORE', aiTitle: 'Your product guide, built into the store.', aiLead: 'Ask in English or Arabic. Qimia AI checks current products, prices, availability and delivery context before narrowing your options.', promptProtein: 'Find a protein for lean muscle', promptCompare: 'Compare two products', promptDelivery: 'Check delivery for my country', openQimiaAI: 'Open Qimia AI', liveInventory: 'LIVE INVENTORY', aiQuestion: '“I want a simple daily wellness routine.”', checkingStore: 'Checking products, facts and availability…', foundation: 'FOUNDATION', realMatch: 'Real product match', reasonPreview: 'Chosen from verified catalogue details—not a generic list.', englishArabic: 'English + العربية', privateSessionShort: 'Context stays in this tab', factsServing: 'Serving details', factsServingSub: 'Read from real product labels where available', factsActives: 'Key actives', factsActivesSub: 'Shown with the listed amount, never guessed', factsPrice: 'Live price + stock', factsPriceSub: 'Synced with WooCommerce and your market', factsSafety: 'Clear uncertainty', factsSafetySub: 'Missing facts stay marked as not listed',
			compareLab: 'COMPARE LAB', compareTitle: 'Know the difference. Choose with confidence.', compareLead: 'Choose two products and compare verified live details side by side.', openCompare: 'Open comparison', methodKicker: 'CLEAR BY DESIGN', methodTitle: 'From goal to shortlist in three clear steps.', stepOne: 'Set your outcome', stepOneSub: 'Choose what you want to support with focused, non-medical filters.', stepTwo: 'See the real details', stepTwoSub: 'Every result shows the available label facts, live price and purchase state.', stepThree: 'Ask Qimia AI live', stepThreeSub: 'Continue in the live assistant without leaving the store or losing your context.', stackKicker: 'AI ROUTINE BUILDER', stackTitle: 'Turn products into a simple routine.', stackLead: 'Use Qimia AI to organise a clear morning, training and evening plan around one goal and one budget. No diagnosis. No unnecessary extras.', buildRoutine: 'Build with Qimia AI', trustKicker: 'QIMIA STANDARD', trustTitle: 'Digital intelligence. Local accountability.', trustLead: 'Technology should make supplement shopping more transparent—not more confusing. Every suggestion stays connected to a real product in the Qimia store.', authentic: 'Authentic products', authenticSub: 'Sourced through trusted distribution channels.', factsFirst: 'Facts before hype', factsFirstSub: 'Labels, servings and product details in plain language.', localSupport: 'Live store intelligence', localSupportSub: 'AI stays connected to current Qimia products, stock and prices.', gccDelivery: 'Oman & GCC delivery', gccDeliverySub: 'Availability and fulfilment from a regional store.', footerLine: 'Smarter supplement choices, grounded in real products and clear facts.', disclaimer: 'Qimia Intelligence Lab provides shopping guidance only and does not diagnose, treat or replace medical advice.', compareProducts: 'Compare products', compareNow: 'Compare now', sideBySide: 'Your shortlist, side by side.',
			inStock: 'In stock', outOfStock: 'Out of stock', servings: 'Servings', servingSize: 'Serving size', perServing: 'per serving', caffeineFact: 'Caffeine', keyActive: 'Key active', purpose: 'Practical use', protein: 'Protein', creatine: 'Creatine', collagen: 'Collagen', details: 'View product', addToBag: 'Add to cart', addMobile: 'Add', selectOptions: 'Select options', optionsMobile: 'Options', askAbout: 'Discuss with Qimia AI', addCompare: 'Compare', removeCompare: 'Remove', remove: 'Remove', foundationPick: 'Foundation', targetedPick: 'Targeted support', optionalPick: 'Optional', notListed: 'Not listed', selected: 'Selected', comparisonEmpty: 'Choose two product cards to build a useful comparison.', product: 'Product', price: 'Price', dietaryFit: 'Dietary fit', maxCompare: 'Qimia AI compares two products at a time.', addedCompare: 'Product added. Choose a second product to compare.', removedCompare: 'Removed from comparison.', cartAdded: 'Added to your cart', viewCart: 'View cart', verified: 'Verified', askAICompare: 'Open this in Qimia AI', compareReady: 'Live comparison ready', aiConnecting: 'Connecting to Qimia AI…', aiUnavailable: 'Qimia AI could not open. Please try again.', aiProductReady: 'Product ready in Qimia AI—tap Send to discuss it.', quickViewTitle: 'Choose your options', quickViewLoading: 'Loading live options…', quickViewError: 'Options could not load. View the product for full details.', quickViewUnavailable: 'No purchasable in-stock options are available right now.', chooseOption: 'Choose', selectAllOptions: 'Choose an option for each field.', checkingVariation: 'Checking price and availability…', variationUnavailable: 'That combination is currently unavailable.', addSelectedToBag: 'Add selected to bag', viewFullProduct: 'View full product', close: 'Close', itemsInCart: 'items in cart', offer: 'Offer', discountFact: 'Discount', expiryFact: 'Expiry', daysLeftFact: 'Offer window', daysRemaining: 'days remaining', stockFact: 'Stock', orderLimitFact: 'Order limit', maximumUnits: 'maximum', bestDeal: 'Best deal', unitsAvailable: 'available',
			purposeProtein: 'Supports daily protein intake and post-training muscle recovery', purposeCreatine: 'Built for repeated high-intensity training and strength-focused routines', purposeMassGainer: 'Adds convenient calories and protein to a weight-gain routine', purposePreWorkout: 'Designed for pre-training energy, focus and performance support', purposeFatBurner: 'Targeted support for calorie-controlled training phases', purposeAminoRecovery: 'Provides amino-acid support around training and recovery', purposeHydration: 'Helps replenish fluids and electrolytes around activity', purposeJointSupport: 'Targeted support for joint comfort and everyday mobility', purposeSleepSupport: 'Designed to support a consistent evening and sleep routine', purposeBeautySupport: 'Targeted collagen or biotin support for daily beauty care', purposeOmegaSupport: 'Daily omega-fatty-acid support for general wellness', purposeDailyWellness: 'Convenient daily micronutrient support for dietary gaps', purposeGoalSupport: 'A goal-specific formula grounded in the listed product details',
			flashSale: 'FLASH', regularPrice: 'Regular price', salePrice: 'Sale price', fromPrice: 'From', searchingStore: 'Searching the live store…', searchKeepTyping: 'Keep typing to search the whole store…', searchUnavailable: 'Live search is unavailable—showing indexed matches.', noSearchResults: 'No matching products found.', viewAllResults: 'View all search results', bestSeller: 'Best seller', proteinPick: 'Protein pick', creatinePick: 'Creatine pick', previousProducts: 'Previous products', nextProducts: 'Next products', intentNudge: 'You have explored this goal or several products. Let Qimia AI narrow the shortlist when you are ready.', intentNudgeAction: 'Refine with Qimia AI', dismiss: 'Dismiss', fatLoss: 'Fat loss', performance: 'Performance', closestMatches: 'Closest verified matches', popularStartingPoints: 'Popular starting points', fallbackReason: 'No exact verified match met every filter, so these are the nearest in-stock options.', fallbackPopularReason: 'Start with these live in-stock products, or let Qimia AI narrow the catalogue.', askRefine: 'Ask Qimia to refine', heroPromptEmpty: 'Tell Qimia what you want to achieve first.', heroPromptConnecting: 'Connecting your request to Qimia AI…', heroPromptSent: 'Your request is ready in Qimia AI.', aiVerdict: 'QIMIA AI VERDICT'
		},
		ar: {
			delivery: config.deliveryMessageAr || config.deliveryMessage || 'تظهر خيارات التوصيل لدول الخليج عند إتمام الطلب', stage: 'نسخة تجريبية خاصة · السلة مفعّلة · الدفع متوقف', menu: 'المتجر', navShop: 'المتجر', navGoals: 'الأهداف', navLab: 'ذكاء كيميا', navCompare: 'المقارنة', askQimia: 'اسأل ذكاء كيميا', searchPlaceholder: 'ابحث عن منتج أو مكوّن أو هدف…', eyebrow: 'مختبر ذكاء كيميا · بيانات المتجر المباشرة', heroOne: 'أذكى طريق إلى', heroTwo: 'المكمّل المناسب.', heroLead: 'منتجات مباشرة وحقائق موثّقة وذكاء كيميا—مسار واضح من الهدف إلى السلة.', buildWithAI: 'ابنِ روتيني بالذكاء', live: 'مباشر', findMatch: 'استكشف حسب الهدف', heroNote: 'يبقى سياق التصفح في هذه الصفحة؛ وتُعالج الرسائل التي ترسلها عبر ذكاء كيميا', supplements: 'منتج ومكمّل', brands: 'علامة موثوقة', muscatStore: 'متجر في مسقط', goalLabel: 'استكشف حسب الهدف', storeData: 'بيانات المتجر', liveCatalogue: 'ذكاء كيميا جاهز', availabilityChecked: 'اسأل عن أي منتج مباشر', ingredients: 'المكوّنات', servingsLabel: 'الحصص', valueLabel: 'القيمة', scrollDiscover: 'مرّر لاستكشاف المتجر الرقمي',
			startOutcome: 'ابدأ بالنتيجة التي تريدها', whatGoal: 'ما الذي ترغب في دعمه؟', chooseGoal: 'اختر هدفاً واحداً، ثم خصّص القائمة المختصرة.', privateSession: 'يبقى سياق التصفح في هذه الصفحة؛ وتُعالج الرسائل التي ترسلها عبر ذكاء كيميا', muscle: 'بناء العضلات', muscleSub: 'بروتين · قوة · كتلة', fatLoss: 'إدارة الدهون', fatLossSub: 'دعم التنشيف · القيمة · التوفّر', performance: 'الأداء', performanceSub: 'قبل التمرين · كرياتين · أمينو', energy: 'الطاقة والتركيز', energySub: 'نشاط · وضوح · أداء', recovery: 'التعافي', recoverySub: 'ترطيب · استشفاء · حركة', sleep: 'النوم والهدوء', sleepSub: 'راحة · روتين · توازن', wellness: 'العافية اليومية', wellnessSub: 'فيتامينات · أمعاء · مناعة', beauty: 'الشعر والبشرة والجمال', beautySub: 'كولاجين · بيوتين · نضارة', refine: 'فلاتر موثّقة:', stimFree: 'بدون منشّطات', vegan: 'مناسب للنباتيين', bestValue: 'قيمة الحصة', liveMatches: 'نتائج مباشرة من الكتالوج', selectedFor: 'مختارة لهدف', rankingNote: 'الترتيب حسب الملاءمة والبيانات الموثّقة والتوفّر، لا حسب حجم الخصم.', noMatches: 'لا توجد نتيجة موثّقة قريبة حالياً', noMatchesSub: 'جرّب هدفاً آخر أو امسح البحث أو أزل الفلتر الصارم.', showMore: 'عرض منتجات أكثر',
			aiConnected: 'ذكاء كيميا · متصل بالمتجر المباشر', aiTitle: 'دليلك للمنتجات، داخل المتجر.', aiLead: 'اسأل بالعربية أو الإنجليزية. يتحقق ذكاء كيميا من المنتجات والأسعار والتوفّر وسياق التوصيل قبل تضييق الخيارات.', promptProtein: 'ابحث عن بروتين للعضلات الخفيفة', promptCompare: 'قارن بين منتجين', promptDelivery: 'تحقق من التوصيل إلى بلدي', openQimiaAI: 'افتح ذكاء كيميا', liveInventory: 'مخزون مباشر', aiQuestion: '«أريد روتيناً بسيطاً للعافية اليومية»', checkingStore: 'جارٍ فحص المنتجات والحقائق والتوفّر…', foundation: 'الأساس', realMatch: 'منتج حقيقي مناسب', reasonPreview: 'مختار من بيانات الكتالوج الموثّقة، لا من قائمة عامة.', englishArabic: 'العربية + English', privateSessionShort: 'يبقى السياق في هذه الصفحة', factsServing: 'تفاصيل الحصص', factsServingSub: 'تُقرأ من ملصق المنتج الحقيقي عند توفرها', factsActives: 'المواد الفعالة', factsActivesSub: 'تظهر بالكمية المدرجة من دون تخمين', factsPrice: 'السعر والمخزون المباشران', factsPriceSub: 'متزامنان مع ووكومرس وسوقك', factsSafety: 'وضوح عند نقص البيانات', factsSafetySub: 'المعلومة الناقصة تبقى مميزة بأنها غير مدرجة',
			compareLab: 'مختبر المقارنة', compareTitle: 'اعرف الفرق واختر بثقة.', compareLead: 'اختر منتجين وقارن التفاصيل المباشرة الموثّقة جنباً إلى جنب.', openCompare: 'افتح المقارنة', methodKicker: 'وضوح من البداية', methodTitle: 'من الهدف إلى قائمة مختصرة في ثلاث خطوات واضحة.', stepOne: 'حدّد هدفك', stepOneSub: 'اختر ما تريد دعمه بفلاتر مركّزة وغير طبية.', stepTwo: 'شاهد التفاصيل الحقيقية', stepTwoSub: 'كل نتيجة تعرض حقائق الملصق المتوفرة والسعر وحالة الشراء.', stepThree: 'اسأل ذكاء كيميا مباشرة', stepThreeSub: 'تابع في المساعد المباشر من دون مغادرة المتجر أو فقدان السياق.', stackKicker: 'منشئ الروتين بالذكاء', stackTitle: 'حوّل المنتجات إلى روتين بسيط.', stackLead: 'استخدم ذكاء كيميا لتنظيم روتين واضح للصباح والتمرين والمساء حول هدف وميزانية واحدة، بلا تشخيص أو إضافات غير ضرورية.', buildRoutine: 'ابنِ روتيني مع كيميا', trustKicker: 'معيار كيميا', trustTitle: 'ذكاء رقمي ومسؤولية محلية.', trustLead: 'يجب أن تجعل التقنية شراء المكمّلات أوضح، لا أكثر تعقيداً. كل اقتراح يبقى مرتبطاً بمنتج حقيقي في متجر كيميا.', authentic: 'منتجات أصلية', authenticSub: 'من قنوات توزيع موثوقة.', factsFirst: 'الحقائق قبل الضجيج', factsFirstSub: 'الملصق والحصص والتفاصيل بلغة واضحة.', localSupport: 'ذكاء المتجر المباشر', localSupportSub: 'يبقى الذكاء متصلاً بمنتجات كيميا ومخزونها وأسعارها الحالية.', gccDelivery: 'توصيل عُمان والخليج', gccDeliverySub: 'توفر وتنفيذ من متجر إقليمي.', footerLine: 'اختيارات أذكى للمكمّلات، مبنية على منتجات حقيقية وحقائق واضحة.', disclaimer: 'مختبر كيميا أداة للمساعدة في التسوق فقط، ولا يشخّص أو يعالج أو يستبدل الاستشارة الطبية.', compareProducts: 'قارن المنتجات', compareNow: 'قارن الآن', sideBySide: 'قائمتك المختصرة جنباً إلى جنب.',
			inStock: 'متوفر', outOfStock: 'غير متوفر', servings: 'الحصص', servingSize: 'حجم الحصة', perServing: 'للحصة', caffeineFact: 'الكافيين', keyActive: 'المادة الفعالة', purpose: 'الاستخدام العملي', protein: 'البروتين', creatine: 'الكرياتين', collagen: 'الكولاجين', details: 'عرض المنتج', addToBag: 'أضف للسلة', addMobile: 'أضف', selectOptions: 'اختر الخيارات', optionsMobile: 'الخيارات', askAbout: 'ناقشه مع ذكاء كيميا', addCompare: 'قارن', removeCompare: 'إزالة', remove: 'إزالة', foundationPick: 'الأساس', targetedPick: 'دعم موجّه', optionalPick: 'اختياري', notListed: 'غير مدرج', selected: 'محدد', comparisonEmpty: 'اختر منتجين لبناء مقارنة مفيدة.', product: 'المنتج', price: 'السعر', dietaryFit: 'الملاءمة الغذائية', maxCompare: 'يقارن ذكاء كيميا منتجين في كل مرة.', addedCompare: 'تمت إضافة المنتج. اختر منتجاً ثانياً للمقارنة.', removedCompare: 'تمت الإزالة من المقارنة.', cartAdded: 'تمت الإضافة إلى سلتك', viewCart: 'عرض السلة', verified: 'موثّق', askAICompare: 'افتحها في ذكاء كيميا', compareReady: 'المقارنة المباشرة جاهزة', aiConnecting: 'جارٍ الاتصال بذكاء كيميا…', aiUnavailable: 'تعذر فتح ذكاء كيميا. حاول مرة أخرى.', aiProductReady: 'المنتج جاهز في ذكاء كيميا—اضغط إرسال لمناقشته.', quickViewTitle: 'اختر خياراتك', quickViewLoading: 'جارٍ تحميل الخيارات المباشرة…', quickViewError: 'تعذر تحميل الخيارات. افتح صفحة المنتج للتفاصيل الكاملة.', quickViewUnavailable: 'لا تتوفر حالياً خيارات قابلة للشراء وموجودة في المخزون.', chooseOption: 'اختر', selectAllOptions: 'اختر قيمة لكل خيار.', checkingVariation: 'جارٍ التحقق من السعر والتوفر…', variationUnavailable: 'هذه التوليفة غير متوفرة حالياً.', addSelectedToBag: 'أضف الاختيار للسلة', viewFullProduct: 'عرض المنتج كاملاً', close: 'إغلاق', itemsInCart: 'منتجات في السلة', offer: 'العرض', discountFact: 'الخصم', expiryFact: 'تاريخ الانتهاء', daysLeftFact: 'مدة العرض', daysRemaining: 'يوماً متبقياً', stockFact: 'المخزون', orderLimitFact: 'حد الطلب', maximumUnits: 'كحد أقصى', bestDeal: 'أفضل عرض', unitsAvailable: 'متوفر',
			purposeProtein: 'يدعم استكمال احتياجك اليومي من البروتين والتعافي بعد التمرين', purposeCreatine: 'مصمم لدعم التمارين عالية الشدة المتكررة وروتين القوة', purposeMassGainer: 'يوفر سعرات وبروتيناً بصورة عملية ضمن روتين زيادة الوزن', purposePreWorkout: 'مصمم لدعم الطاقة والتركيز والأداء قبل التمرين', purposeFatBurner: 'دعم موجّه لمراحل التدريب مع التحكم بالسعرات', purposeAminoRecovery: 'يوفر أحماضاً أمينية لدعم ما حول التمرين والتعافي', purposeHydration: 'يدعم تعويض السوائل والإلكتروليتات مع النشاط', purposeJointSupport: 'دعم موجّه لراحة المفاصل والحركة اليومية', purposeSleepSupport: 'مصمم لدعم روتين مسائي ونوم منتظم', purposeBeautySupport: 'دعم موجّه بالكولاجين أو البيوتين للعناية اليومية', purposeOmegaSupport: 'دعم يومي بأحماض أوميغا الدهنية للعافية العامة', purposeDailyWellness: 'دعم يومي عملي للمغذيات الدقيقة وسد الفجوات الغذائية', purposeGoalSupport: 'تركيبة موجّهة لهدف واضح ومبنية على تفاصيل المنتج المدرجة',
			flashSale: 'خاطف', regularPrice: 'السعر العادي', salePrice: 'سعر التخفيض', fromPrice: 'ابتداءً من', searchingStore: 'جارٍ البحث في المتجر المباشر…', searchKeepTyping: 'تابع الكتابة للبحث في المتجر بالكامل…', searchUnavailable: 'البحث المباشر غير متاح—نعرض نتائج الفهرس المحلي.', noSearchResults: 'لم نعثر على منتجات مطابقة.', viewAllResults: 'عرض جميع نتائج البحث', bestSeller: 'الأكثر مبيعاً', proteinPick: 'اختيار بروتين', creatinePick: 'اختيار كرياتين', previousProducts: 'المنتجات السابقة', nextProducts: 'المنتجات التالية', intentNudge: 'استكشفت هذا الهدف أو عدة منتجات. يمكن لذكاء كيميا تضييق القائمة عندما تكون جاهزاً.', intentNudgeAction: 'خصّصها مع ذكاء كيميا', dismiss: 'إغلاق', fatLoss: 'إدارة الدهون', performance: 'الأداء', closestMatches: 'أقرب النتائج الموثّقة', popularStartingPoints: 'نقاط بداية شائعة', fallbackReason: 'لم تطابق نتيجة موثّقة كل الفلاتر، لذا نعرض أقرب الخيارات المتوفرة.', fallbackPopularReason: 'ابدأ بهذه المنتجات المتوفرة، أو دع ذكاء كيميا يضيّق الكتالوج.', askRefine: 'اطلب من كيميا تحسين النتائج', heroPromptEmpty: 'أخبر كيميا أولاً بما تريد تحقيقه.', heroPromptConnecting: 'جارٍ ربط طلبك بذكاء كيميا…', heroPromptSent: 'طلبك جاهز في ذكاء كيميا.', aiVerdict: 'خلاصة ذكاء كيميا'
		}
	};

	const $ = (selector, scope = document) => scope.querySelector(selector);
	const $$ = (selector, scope = document) => [...scope.querySelectorAll(selector)];
	const t = key => (copy[state.lang] && copy[state.lang][key]) || copy.en[key] || key;
	const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));
	const safeUrl = value => { try { const url = new URL(String(value || ''), window.location.href); return ['http:', 'https:'].includes(url.protocol) ? url.href : '#'; } catch (_) { return '#'; } };
	const plain = value => String(value ?? '').replace(/\s+/g, ' ').trim();
	const sessionIntentKey = 'qil-session-intent-v1';
	function readSessionIntent() {
		try {
			const stored = JSON.parse(window.sessionStorage.getItem(sessionIntentKey) || '{}');
			return {
				goalHits: stored?.goalHits && typeof stored.goalHits === 'object' ? stored.goalHits : {},
				productInteractions: Math.max(0, Math.min(9, Number(stored?.productInteractions || (Array.isArray(stored?.productIds) ? new Set(stored.productIds).size : 0)) || 0)),
				nudgeShown: stored?.nudgeShown === true
			};
		} catch (_) {
			return {goalHits:{}, productInteractions:0, nudgeShown:false};
		}
	}
	const sessionIntent = readSessionIntent();
	let lastSessionProductId = 0;
	function saveSessionIntent() {
		try { window.sessionStorage.setItem(sessionIntentKey, JSON.stringify(sessionIntent)); } catch (_) { /* Session storage can be disabled without affecting the store. */ }
	}
	// Rewrite a legacy record immediately so raw product IDs from an older build
	// are reduced to the anonymous interaction counter above.
	saveSessionIntent();
	function dismissIntentNudge() {
		$('[data-qil-intent-nudge]')?.remove();
		sessionIntent.nudgeShown = true;
		saveSessionIntent();
	}
	function maybeShowIntentNudge() {
		if (sessionIntent.nudgeShown || $('[data-qil-intent-nudge]')) return;
		const repeatedGoal = Object.values(sessionIntent.goalHits).some(value => Number(value) >= 2);
		const repeatedProducts = sessionIntent.productInteractions >= 3;
		if (!repeatedGoal && !repeatedProducts) return;
		const grid = $('[data-qil-results]');
		if (!grid?.parentElement) return;
		const nudge = document.createElement('aside');
		nudge.className = 'qil-intent-nudge';
		nudge.dataset.qilIntentNudge = '1';
		nudge.setAttribute('aria-label', t('intentNudge'));
		nudge.innerHTML = `<span>${escapeHtml(t('intentNudge'))}</span><button type="button" data-qimia-ai-open data-qil-ai-intent="${state.goal === 'muscle' ? 'protein' : 'routine'}">${escapeHtml(t('intentNudgeAction'))}</button><button type="button" data-qil-nudge-dismiss aria-label="${escapeHtml(t('dismiss'))}">×</button>`;
		grid.parentElement.insertBefore(nudge, grid);
		sessionIntent.nudgeShown = true;
		saveSessionIntent();
	}
	function trackSessionIntent(kind, value) {
		if (kind === 'goal' && Object.prototype.hasOwnProperty.call(goalLabels(), value)) {
			sessionIntent.goalHits[value] = Math.min(9, Number(sessionIntent.goalHits[value] || 0) + 1);
		} else if (kind === 'product') {
			const productId = Math.max(0, Number(value) || 0);
			if (!productId || lastSessionProductId === productId) return;
			lastSessionProductId = productId;
			sessionIntent.productInteractions = Math.min(9, Number(sessionIntent.productInteractions || 0) + 1);
		} else {
			return;
		}
		saveSessionIntent();
		window.setTimeout(maybeShowIntentNudge, 450);
	}
	const localizeFactValue = value => {
		let output = plain(value);
		if (state.lang !== 'ar' || !output) return output;
		const replacements = [
			[/\bL-Citrulline\b/gi, 'إل-سيترولين'], [/\bBeta-Alanine\b/gi, 'بيتا ألانين'],
			[/\bCreatine\b/gi, 'كرياتين'], [/\bCaffeine\b/gi, 'كافيين'],
			[/\bProtein\b/gi, 'بروتين'], [/\bCollagen\b/gi, 'كولاجين'],
			[/\bGlutamine\b/gi, 'جلوتامين'], [/\bMagnesium\b/gi, 'مغنيسيوم'],
			[/\bMelatonin\b/gi, 'ميلاتونين'], [/\bBiotin\b/gi, 'بيوتين'],
			[/\bservings?\b/gi, 'حصة'], [/\bscoops?\b/gi, 'مكيال'],
			[/\bcapsules?\b/gi, 'كبسولة'], [/\btablets?\b/gi, 'قرص'],
			[/\bsoftgels?\b/gi, 'كبسولة هلامية']
		];
		replacements.forEach(([pattern, replacement]) => { output = output.replace(pattern, replacement); });
		return output;
	};
	const brandName = product => plain(product?.brand?.name || product?.brand || 'Qimia');
	const categoryNames = product => (Array.isArray(product?.categories) ? product.categories : []).map(item => plain(item?.name || item)).filter(Boolean);
	function minimumPriceHtml(entry, product = null) {
		const html = plain(entry?.minimumFormattedHtml || entry?.formattedMinimumHtml);
		if (html) return html;
		const rawMinimum = entry?.min ?? entry?.value;
		const minimum = rawMinimum === undefined || rawMinimum === null || rawMinimum === '' ? Number.NaN : Number(rawMinimum);
		if (Number.isFinite(minimum) && minimum >= 0) return moneyMarkup(minimum, product);
		return plain(entry?.formattedHtml) || escapeHtml(plain(entry?.formatted || '—'));
	}
	function visualPriceText(entry, product = null) {
		const html = plain(entry?.minimumFormattedHtml || entry?.formattedMinimumHtml || entry?.formattedHtml);
		if (html) {
			const template = document.createElement('template');
			template.innerHTML = html;
			template.content.querySelectorAll('.screen-reader-text, .screen-reader-text-before, .screen-reader-text-after').forEach(node => node.remove());
			const text = plain(template.content.textContent);
			if (text) return text;
		}
		const minimum = Number(entry?.min ?? entry?.value);
		if (Number.isFinite(minimum)) return spokenPrice(entry, product);
		return plain(entry?.minimumFormatted || entry?.formatted || '');
	}
	const priceLabel = product => {
		const price = product?.price || {};
		return visualPriceText(price.sale || price.current || price, product) || plain(product?.priceLabel || '—');
	};
	const priceNumber = entry => Number(entry?.min ?? entry?.value ?? 0);
	const priceEntryHtml = (entry, product = null) => minimumPriceHtml(entry, product);
	const hasVisibleSale = product => {
		const price = product?.price || {}, current = price.sale || price.current || price, regular = price.regular;
		return Boolean(price.onSale && price.sale && regular)
			&& priceNumber(regular) > priceNumber(current) + 0.00001;
	};
	const variablePricePrefix = product => product?.type === 'variable' && product?.exactRepeatSelection !== true
		? `<small class="qil-price-from" dir="auto">${escapeHtml(t('fromPrice'))}</small>`
		: '';
	function spokenPrice(entry, product) {
		const currency = plain(product?.price?.currency || config.currency || '').toUpperCase();
		const minimum = Number(entry?.min ?? entry?.value);
		if (!Number.isFinite(minimum)) return plain(entry?.minimumFormatted || entry?.formatted || '—');
		const format = value => {
			try {
				return new Intl.NumberFormat(state.lang === 'ar' ? 'ar-OM' : 'en-OM', {
					style: /^[A-Z]{3}$/.test(currency) ? 'currency' : 'decimal',
					currency: /^[A-Z]{3}$/.test(currency) ? currency : undefined,
					currencyDisplay: 'code'
				}).format(value);
			} catch (_) {
				return `${value} ${currency}`.trim();
			}
		};
		return format(minimum);
	}
	function priceMarkup(product) {
		const price = product?.price || {}, current = price.sale || price.current || price, regular = price.regular;
		const prefix = variablePricePrefix(product);
		if (hasVisibleSale(product)) {
			const regularAria = `${t('regularPrice')}: ${spokenPrice(regular, product)}`;
			const saleAria = `${t('salePrice')}: ${spokenPrice(current, product)}`;
			return `<span class="qil-price-values" dir="ltr">${prefix}<del class="qil-regular-price" aria-label="${escapeHtml(regularAria)}"><span aria-hidden="true">${priceEntryHtml(regular, product)}</span></del><strong class="qil-sale-price" aria-label="${escapeHtml(saleAria)}"><span aria-hidden="true">${priceEntryHtml(current, product)}</span></strong></span>`;
		}
		return `<span class="qil-price-values" dir="ltr">${prefix}<strong class="qil-current-price" aria-label="${escapeHtml(`${t('price')}: ${spokenPrice(current, product)}`)}"><span aria-hidden="true">${priceEntryHtml(current, product)}</span></strong></span>`;
	}
	function comparePriceMarkup(product) {
		const price = product?.price || {}, current = price.sale || price.current || price, regular = price.regular;
		const prefix = variablePricePrefix(product);
		if (hasVisibleSale(product)) {
			const label = `${t('regularPrice')}: ${spokenPrice(regular, product)}; ${t('salePrice')}: ${spokenPrice(current, product)}`;
			return `<span class="qil-compare-price-value" dir="ltr" aria-label="${escapeHtml(label)}">${prefix}<del aria-hidden="true">${priceEntryHtml(regular, product)}</del><strong aria-hidden="true">${priceEntryHtml(current, product)}</strong></span>`;
		}
		return `<span class="qil-compare-price-value" dir="ltr">${prefix}<strong class="qil-compare-current-price" aria-label="${escapeHtml(`${t('price')}: ${spokenPrice(current, product)}`)}"><span aria-hidden="true">${priceEntryHtml(current, product)}</span></strong></span>`;
	}
	function perServingMarkup(product) {
		const price = product?.price || {};
		const value = plain(price.perServingHtml || price.perServingFormatted || product?.perServing);
		if (!value || price.perServingVerified !== true) return '';
		const rendered = plain(price.perServingHtml) ? price.perServingHtml : escapeHtml(value);
		return `<span class="qil-per-serving" dir="ltr">${price.perServingBasis?.variationId ? variablePricePrefix(product) : ''}${rendered} <bdi>${escapeHtml(t('perServing'))}</bdi></span>`;
	}
	const goalLabels = () => ({muscle:t('muscle'),'fat-loss':t('fatLoss'),performance:t('performance'),energy:t('energy'),recovery:t('recovery'),sleep:t('sleep'),wellness:t('wellness'),beauty:t('beauty')});
	function syncGoalControls() {
		const catalogueGoal = state.goal;
		$$('.qil-goal[data-goal]').forEach(button => {
			const active = button.dataset.goal === catalogueGoal;
			button.classList.toggle('is-active', active);
			button.setAttribute(button.getAttribute('role') === 'radio' ? 'aria-checked' : 'aria-pressed', active ? 'true' : 'false');
		});
		const portalGoal = state.goal;
		$$('[data-qil-hero-ai-chip][data-goal]').forEach(button => {
			const active = button.dataset.goal === portalGoal;
			button.classList.toggle('is-active', active);
			button.setAttribute('aria-pressed', active ? 'true' : 'false');
		});
	}

	/* ---------------------------------------------------------------------
	   The Qimia AI Translator decides a request's language from an explicit
	   X-Qimia-Language header before it falls back to a cookie or the Referer
	   (QAATM_Dynamic::requested_arabic). Stating it on every request this page
	   makes keeps REST payloads and WooCommerce cart fragments on the same
	   language as the page they were requested from, instead of depending on a
	   cookie that a page cache may have varied away.
	   --------------------------------------------------------------------- */
	function languageHeaders(headers) {
		const out = Object.assign({}, headers || {});
		out['X-Qimia-Language'] = state.lang === 'ar' ? 'ar' : 'en';
		return out;
	}
	function setupThemeAjaxLanguage() {
		const jq = window.jQuery;
		// .ajaxSend() lives on jQuery.fn; the static jQuery.ajaxSend never exists.
		if (!jq || !jq.fn || typeof jq.fn.ajaxSend !== 'function') return;
		jq(document).ajaxSend((event, xhr, settings) => {
			let target;
			try { target = new URL(String(settings?.url || ''), window.location.href); } catch (error) { return; }
			// Same-origin only: never leak the page language to a third party
			// (and never turn a cross-origin call into a preflighted one).
			if (target.origin !== window.location.origin) return;
			try { xhr.setRequestHeader('X-Qimia-Language', state.lang === 'ar' ? 'ar' : 'en'); } catch (error) { /* header already sent */ }
		});
	}

	/* ---------------------------------------------------------------------
	   Collection and brand carousels.

	   The rails are plain scroll-snap containers, so they work with a finger,
	   a trackpad and a keyboard on their own; these arrows page them by one
	   full view. scrollLeft is negative in RTL on every engine this theme
	   supports, so paging is expressed as a signed delta from the current
	   position rather than an absolute offset.
	   --------------------------------------------------------------------- */
	function railStep(rail) {
		const first = rail.firstElementChild;
		if (!first) return rail.clientWidth;
		const style = getComputedStyle(rail);
		const gap = parseFloat(style.columnGap || style.gap || '0') || 0;
		const item = first.getBoundingClientRect().width + gap;
		if (!item) return rail.clientWidth;
		// Page by whole cards, and never by less than one.
		return Math.max(item, Math.floor(rail.clientWidth / item) * item);
	}
	function syncRailNav(rail) {
		const key = rail.dataset.qilRail || '';
		const nav = key ? document.querySelector(`[data-qil-rail-nav="${CSS.escape(key)}"]`) : null;
		const overflow = rail.scrollWidth - rail.clientWidth;
		if (nav) nav.hidden = overflow <= 4;
		if (!nav) return;
		const position = Math.abs(rail.scrollLeft);
		const prev = nav.querySelector('[data-qil-rail-prev]');
		const next = nav.querySelector('[data-qil-rail-next]');
		if (prev) prev.disabled = position <= 4;
		if (next) next.disabled = position >= overflow - 4;
	}
	function scrollRail(rail, direction) {
		const rtl = getComputedStyle(rail).direction === 'rtl';
		const delta = railStep(rail) * direction * (rtl ? -1 : 1);
		const behavior = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth';
		rail.scrollBy({left: delta, behavior});
	}
	function railFor(node) {
		const nav = node.closest('[data-qil-rail-nav]');
		if (!nav) return null;
		const key = nav.dataset.qilRailNav || '';
		return key ? document.querySelector(`[data-qil-rail="${CSS.escape(key)}"]`) : null;
	}
	function setupRails() {
		$$('[data-qil-rail]').forEach(rail => {
			if (rail.dataset.qilRailReady) { syncRailNav(rail); return; }
			rail.dataset.qilRailReady = '1';
			rail.addEventListener('scroll', () => {
				window.clearTimeout(rail._qilRailTimer);
				rail._qilRailTimer = window.setTimeout(() => syncRailNav(rail), 90);
			}, {passive: true});
			if (typeof ResizeObserver === 'function') {
				const observer = new ResizeObserver(() => syncRailNav(rail));
				observer.observe(rail);
			}
			syncRailNav(rail);
		});
	}

	/* ---------------------------------------------------------------------
	   Off the homepage the chrome is a fixed bar, because a sticky element only
	   sticks inside its own parent and the chrome's parent is the short wrapper
	   that holds it. Its height is measured rather than assumed — the topbar
	   wraps at some widths and the header is a different height in Arabic — and
	   published so the spacer under it always matches.
	   --------------------------------------------------------------------- */
	/* The admin bar is 32px and fixed on a desktop, 46px and absolute on a
	   phone, and WordPress pays for it with a margin on <html>. A fixed element
	   is positioned against the viewport and ignores that margin, so the chrome
	   sat underneath the bar until it was told how far down to start. */
	function syncAdminBarOffset() {
		const bar = document.getElementById('wpadminbar');
		let offset = 0;
		if (bar) {
			const position = getComputedStyle(bar).position;
			// An absolute bar scrolls away with the page; only a fixed one keeps
			// occupying the top of the viewport.
			offset = position === 'fixed' ? Math.round(bar.getBoundingClientRect().height) : 0;
		}
		document.documentElement.style.setProperty('--qil-admin-bar-h', `${offset}px`);
		return offset;
	}
	/* The height the page reserves must NOT depend on whether the promo bar is
	   collapsed. Measuring the live box while that bar animates changed the
	   spacer, which changed the document height, which moved the scroll position
	   under the reader's finger — measured on staging as a jump from 300 back to
	   266 on every scroll, with a ResizeObserver firing through the whole 250ms
	   transition. The natural height is measured from the parts instead, so the
	   value is constant and the collapse is purely visual. */
	function syncChromeOffset() {
		const shell = document.querySelector('[data-qil-chrome]');
		if (!shell) return 0;
		const header = shell.querySelector('.qil-header');
		const topbar = shell.querySelector('.qil-topbar');
		const headerHeight = header ? header.getBoundingClientRect().height : 0;
		// scrollHeight ignores the max-height the collapse animates.
		const topbarHeight = topbar ? topbar.scrollHeight : 0;
		const height = Math.round(headerHeight + topbarHeight);
		if (!height) return 0;
		const previous = parseFloat(document.documentElement.style.getPropertyValue('--qil-chrome-h')) || 0;
		// Writing the same value back would still invalidate layout, and the
		// observer that watches this element would see it again.
		if (Math.abs(previous - height) < 1) return height;
		document.documentElement.style.setProperty('--qil-chrome-h', `${height}px`);
		return height;
	}
	function setupChromeOffset() {
		const shell = document.querySelector('[data-qil-chrome]');
		syncAdminBarOffset();
		if (!shell) return;
		syncChromeOffset();
		window.addEventListener('resize', () => { syncAdminBarOffset(); syncChromeOffset(); }, {passive: true});
		window.addEventListener('orientationchange', () => window.setTimeout(() => { syncAdminBarOffset(); syncChromeOffset(); }, 250), {passive: true});
		window.setTimeout(() => { syncAdminBarOffset(); syncChromeOffset(); }, 900);
		// Past the first screenful the promo bar steps aside and only the
		// navigation stays. Nothing is remeasured: the reserved height already
		// ignores the collapse, so the page never moves.
		let stuck = false;
		let ticking = false;
		const apply = () => {
			ticking = false;
			const next = window.scrollY > 48;
			if (next === stuck) return;
			stuck = next;
			shell.classList.toggle('is-stuck', stuck);
		};
		window.addEventListener('scroll', () => {
			if (ticking) return;
			ticking = true;
			window.requestAnimationFrame(apply);
		}, {passive: true});
		apply();
	}

	/* ---------------------------------------------------------------------
	   The product page renders the same variation engine the quick view uses,
	   inline instead of in a modal. Everything below reuses that engine; only
	   the markup for the fields is written here.
	   --------------------------------------------------------------------- */
	function renderProductOptions(panel, data, product) {
		if (!panel) return;
		if (data?.unavailable === true || data?.fallback === true || !Array.isArray(data?.attributes) || !data.attributes.length) {
			panel.hidden = true;
			return;
		}
		const defaults = data.defaults && typeof data.defaults === 'object' ? data.defaults : {};
		const fields = data.attributes.map((attribute, index) => {
			const options = Array.isArray(attribute.options) ? attribute.options : [];
			const defaultValue = plain(defaults[attribute.key] || (options.length === 1 ? options[0]?.value : ''));
			const inputId = `qil-pdp-attribute-${index}`;
			return `<div class="qil-qv-attribute"><label for="${inputId}">${escapeHtml(attribute.label)}</label><select id="${inputId}" data-qil-variation-attribute data-attribute-key="${escapeHtml(attribute.key)}"><option value="">${escapeHtml(t('chooseOption'))}</option>${options.map(option => `<option value="${escapeHtml(option.value)}"${plain(option.value) === defaultValue ? ' selected' : ''}>${escapeHtml(option.label)}</option>`).join('')}</select></div>`;
		}).join('');
		const target = $('[data-qil-variation-fields]', panel);
		if (target) target.innerHTML = fields;
		panel.hidden = false;
		const add = $('[data-qil-qv-add]', panel);
		if (add) { add.classList.add('is-disabled'); add.setAttribute('aria-disabled', 'true'); }
		activeQuickView = {modal: panel, data, product, requestId: 0};
		refreshQuickViewOptions(panel);
		if ($$('[data-qil-variation-attribute]', panel).every(select => plain(select.value))) scheduleVariationCheck(panel);
	}
	/* The gallery swaps the stage photo rather than reloading the page. */
	function setupProductGallery() {
		const stage = $('[data-qil-pdp-photo]');
		const thumbs = $$('[data-qil-pdp-thumb]');
		if (!stage || !thumbs.length) return;
		thumbs.forEach(thumb => {
			thumb.addEventListener('click', () => {
				const src = plain(thumb.dataset.qilPdpThumb);
				if (!src) return;
				stage.src = src;
				const srcset = plain(thumb.dataset.qilPdpSrcset);
				if (srcset) stage.srcset = srcset; else stage.removeAttribute('srcset');
				thumbs.forEach(other => other.classList.toggle('is-active', other === thumb));
			});
		});
	}

	/* The bar tracks the merchant's own add-to-cart control, whatever the theme
	   calls it, and only shows once that control has left the screen. */
	function syncProductBuyBarVisibility() {
		const bar = $('[data-qil-buybar]');
		if (!bar) return;
		const compareDock = $('[data-qil-compare-dock]');
		const compareOpen = window.matchMedia('(max-width: 760px)').matches && compareDock && !compareDock.hidden;
		const visible = bar.dataset.qilAnchorPassed === 'true' && !compareOpen;
		bar.classList.toggle('is-visible', visible);
		bar.setAttribute('aria-hidden', visible ? 'false' : 'true');
		bar.inert = !visible;
	}
	function setupProductBuyBar() {
		const bar = $('[data-qil-buybar]');
		if (!bar) return;
		const anchor = document.querySelector(
			'.single-product-page form.cart, .summary form.cart, .wd-single-add-cart form.cart, form.cart .single_add_to_cart_button, .single-product-page .summary'
		);
		bar.hidden = false;
		const show = passed => {
			const value = String(passed);
			if (bar.dataset.qilAnchorPassed === value) return;
			bar.dataset.qilAnchorPassed = value;
			syncProductBuyBarVisibility();
		};
		show(false);
		if (anchor && typeof IntersectionObserver === 'function') {
			new IntersectionObserver(entries => {
				entries.forEach(entry => show(!entry.isIntersecting && entry.boundingClientRect.bottom <= (entry.rootBounds?.top ?? 40)));
			}, {rootMargin: '-40px 0px 0px 0px'}).observe(anchor);
		}
		// A large scroll can jump over the entire form between IO samples:
		// non-intersecting below -> non-intersecting above produces no event.
		// Check that single edge once per frame, only while the mobile bar can
		// be displayed. Desktop has no listener or geometry reads here.
		const scrollQuery = window.matchMedia('(max-width: 900px)');
		let frame = 0;
		const updateFromScroll = () => {
			frame = 0;
			if (!scrollQuery.matches) return;
			show(anchor ? anchor.getBoundingClientRect().bottom <= 40 : window.scrollY > window.innerHeight * 0.8);
		};
		const schedule = () => { if (!frame) frame = window.requestAnimationFrame(updateFromScroll); };
		const bindScroll = () => {
			window.removeEventListener('scroll', schedule);
			if (scrollQuery.matches) {
				window.addEventListener('scroll', schedule, {passive:true});
				schedule();
			} else {
				window.cancelAnimationFrame(frame);
				frame = 0;
			}
		};
		if (typeof scrollQuery.addEventListener === 'function') scrollQuery.addEventListener('change', bindScroll);
		else if (typeof scrollQuery.addListener === 'function') scrollQuery.addListener(bindScroll);
		bindScroll();
		const query = window.matchMedia('(max-width: 760px)');
		if (typeof query.addEventListener === 'function') query.addEventListener('change', syncProductBuyBarVisibility);
		else if (typeof query.addListener === 'function') query.addListener(syncProductBuyBarVisibility);
		bar.addEventListener('click', event => {
			if (!event.target.closest('[data-qil-buybar-options]')) return;
			event.preventDefault();
			const target = document.querySelector('.single-product-page form.cart, .single-product-page .summary') || anchor;
			target?.scrollIntoView({behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'center'});
		});
	}

	async function initProductPanel() {
		const panel = $('[data-qil-variation-panel]');
		if (!panel) return;
		const id = plain(panel.dataset.qilProductId || config.productId || '');
		if (!id) { panel.hidden = true; return; }
		const product = actionProduct(String(id));
		const endpoint = quickViewEndpoint(id);
		if (!product || !endpoint || !wcAjaxEndpoint('get_variation')) { panel.hidden = true; return; }
		try {
			const data = await loadQuickViewData(id);
			renderProductOptions(panel, data, product);
		} catch (error) {
			panel.hidden = true;
		}
	}

	function applyLanguage() {
		const direction = state.lang === 'ar' ? 'rtl' : 'ltr';
		shells().forEach(shell => { shell.dir = direction; });
		// Only the lab's own document may flip the page. On a theme page this
		// plugin owns a header and a band, not the layout, and turning the whole
		// document RTL rearranged pages nobody asked it to touch.
		if (document.querySelector('#qil-main')) document.documentElement.dir = direction;
		document.documentElement.lang = state.lang;
		$$('.qil-shell [data-i18n], [data-qil-shell] [data-i18n]').forEach(el => { const value = t(el.dataset.i18n); if (value) el.textContent = value; });
		$$('.qil-shell [data-i18n-placeholder], [data-qil-shell] [data-i18n-placeholder]').forEach(el => { el.placeholder = t(el.dataset.i18nPlaceholder); });
		syncGoalControls();
		rebuildRecommendationContext();
		updateGoalLabels(); renderProducts(); renderCollections(); renderCompare(); renderCompareShowcase(); renderEditorialProducts(); hydrateCategoryTiles();
	}

	function setAIOverlayVisibility(node, hidden) {
		if (!node) return;
		if (hidden) {
			if (!aiVisibilityState.has(node)) {
				aiVisibilityState.set(node, {
					value: node.style.getPropertyValue('visibility'),
					priority: node.style.getPropertyPriority('visibility')
				});
			}
			if (node.style.getPropertyValue('visibility') !== 'hidden' || node.style.getPropertyPriority('visibility') !== 'important') node.style.setProperty('visibility', 'hidden', 'important');
			return;
		}
		const previous = aiVisibilityState.get(node);
		if (!previous) return;
		if (previous.value) node.style.setProperty('visibility', previous.value, previous.priority || '');
		else node.style.removeProperty('visibility');
		aiVisibilityState.delete(node);
	}

	function setAIOverlayOffset(node, bottom) {
		if (!node || aiNativePositioned.has(node)) return false;
		let values = aiOffsetState.get(node);
		if (!values) {
			const top = node.style.getPropertyValue('top');
			// Saved positions may come from an older, taller action bar or a
			// different viewport. Start this visit in the safe mobile slot;
			// an actual pointer drag, not delayed hydration, releases the slot.
			values = {
				previousTop: top, previousTopPriority: node.style.getPropertyPriority('top'),
				previousBottom: node.style.getPropertyValue('bottom'), previousBottomPriority: node.style.getPropertyPriority('bottom'),
				top: 'auto', bottom: '', requestedBottom: ''
			};
			aiOffsetState.set(node, values);
		}
		if (values.requestedBottom === bottom && node.style.getPropertyValue('top') === values.top && node.style.getPropertyValue('bottom') === values.bottom) return true;
		if (node.style.getPropertyValue('top') !== 'auto') node.style.setProperty('top', 'auto', 'important');
		node.style.setProperty('bottom', bottom, 'important');
		// Browsers serialize calc() differently. Compare their serialized value
		// on the next observer notification, not the original input string.
		values.top = node.style.getPropertyValue('top');
		values.bottom = node.style.getPropertyValue('bottom');
		values.requestedBottom = bottom;
		return true;
	}

	function setupAILauncherDrag(shadow) {
		let gesture = null;
		shadow.addEventListener('pointerdown', event => {
			const launcher = event.composedPath().find(node => node instanceof Element && node.id === 'aaice-launcher');
			if (!launcher || event.isPrimary === false || (event.button !== undefined && event.button !== 0)) return;
			gesture = {launcher, hint:shadow.querySelector('.aaice-launcher-hint'), id:event.pointerId, x:event.clientX, y:event.clientY};
		}, {capture:true, passive:true});
		window.addEventListener('pointermove', event => {
			if (!gesture || event.pointerId !== gesture.id || Math.hypot(event.clientX - gesture.x, event.clientY - gesture.y) < 6) return;
			// The native assistant continues to perform the drag. This plugin
			// merely stops enforcing its default slot after an actual gesture.
			[gesture.launcher, gesture.hint].filter(Boolean).forEach(node => {
				aiNativePositioned.add(node);
				aiOffsetState.delete(node);
			});
			gesture = null;
		}, {capture:true, passive:true});
		const finish = event => { if (gesture?.id === event.pointerId) gesture = null; };
		window.addEventListener('pointerup', finish, {capture:true, passive:true});
		window.addEventListener('pointercancel', finish, {capture:true, passive:true});
	}

	function clearAIOverlayOffset(node) {
		const values = node ? aiOffsetState.get(node) : null;
		if (!node || !values) return;
		if (node.style.getPropertyValue('top') === values.top) {
			if (values.previousTop) node.style.setProperty('top', values.previousTop, values.previousTopPriority);
			else node.style.removeProperty('top');
		}
		if (node.style.getPropertyValue('bottom') === values.bottom) {
			if (values.previousBottom) node.style.setProperty('bottom', values.previousBottom, values.previousBottomPriority);
			else node.style.removeProperty('bottom');
		}
		aiOffsetState.delete(node);
	}

	// WoodMart's sticky mobile toolbar is fixed at the bottom with z-index 350.
	// Measuring it and publishing the height keeps the compare dock, cart sheet
	// and AI launcher above it without guessing the theme's breakpoint.
	let toolbarHeight = 0;
	function syncThemeToolbarOffset() {
		const toolbar = document.querySelector('.wd-toolbar');
		let height = 0;
		if (toolbar) {
			const styles = window.getComputedStyle(toolbar);
			const visible = styles.display !== 'none' && styles.visibility !== 'hidden' && 'fixed' === styles.position;
			if (visible) height = Math.round(toolbar.getBoundingClientRect().height);
		}
		if (height === toolbarHeight) return height;
		toolbarHeight = height;
		document.documentElement.style.setProperty('--qil-toolbar-h', `${height}px`);
		return height;
	}

	function syncAILauncherOffset() {
		const shadow = document.getElementById('aaice-host')?.shadowRoot;
		const launcher = shadow?.querySelector('#aaice-launcher');
		if (!launcher) return false;
		const hint = shadow.querySelector('.aaice-launcher-hint');
		const modalOpen = Boolean($('.qil-modal:not([hidden])'));
		const mobileViewport = window.matchMedia('(max-width: 760px)').matches;
		const cartDock = document.querySelector('.cart-widget-side');
		const nativeCartOpen = Boolean(
			mobileViewport
				&& cartDock
				&& cartDock.classList.contains('wd-opened')
				&& !cartDock.classList.contains('is-empty')
				&& cartDock.getAttribute('aria-hidden') !== 'true'
		);
		const overlayHidden = modalOpen || nativeCartOpen;
		setAIOverlayVisibility(launcher, overlayHidden);
		setAIOverlayVisibility(hint, overlayHidden);
		if (overlayHidden) return true;
		if (mobileViewport) {
			const bar = syncThemeToolbarOffset();
			// Reserve one compact action-bar slot for the whole visit. Showing
			// a buy or compare bar must not move the assistant while scrolling.
			const managed = setAIOverlayOffset(launcher, `calc(${bar + 16}px + var(--qil-mobile-overlay-reserve, 72px) + env(safe-area-inset-bottom))`);
			if (managed) setAIOverlayOffset(hint, `calc(${bar + 21}px + var(--qil-mobile-overlay-reserve, 72px) + env(safe-area-inset-bottom))`);
			else clearAIOverlayOffset(hint);
		} else {
			clearAIOverlayOffset(launcher);
			clearAIOverlayOffset(hint);
		}
		return true;
	}

	let aiLayoutFrame = 0;
	function scheduleAILauncherOffset() {
		if (aiLayoutFrame) return;
		aiLayoutFrame = window.requestAnimationFrame(() => { aiLayoutFrame = 0; syncAILauncherOffset(); });
	}

	function setupAILauncherOffset() {
		let shadowObserver = null;
		let positionedLauncher = null, positionedHint = null;
		const positionObserver = new MutationObserver(records => {
			if (window.matchMedia('(max-width: 760px)').matches && records.some(record => !aiNativePositioned.has(record.target))) scheduleAILauncherOffset();
		});
		const bind = () => {
			const shadow = document.getElementById('aaice-host')?.shadowRoot;
			if (!shadow) return false;
			if (!shadowObserver) {
				shadowObserver = new MutationObserver(records => {
					const replaced = records.some(record => [...record.addedNodes].some(node => node instanceof Element && (node.matches('#aaice-launcher,.aaice-launcher-hint') || node.querySelector('#aaice-launcher,.aaice-launcher-hint'))));
					if (replaced) bind();
				});
				shadowObserver.observe(shadow, {childList:true, subtree:true});
				setupAILauncherDrag(shadow);
			}
			const launcher = shadow.querySelector('#aaice-launcher');
			const hint = shadow.querySelector('.aaice-launcher-hint');
			if (launcher !== positionedLauncher || hint !== positionedHint) {
				positionObserver.disconnect();
				positionedLauncher = launcher;
				positionedHint = hint;
				[launcher, hint].filter(Boolean).forEach(node => positionObserver.observe(node, {attributes:true, attributeFilter:['style']}));
			}
			return syncAILauncherOffset();
		};
		if (!bind()) {
			const bodyObserver = new MutationObserver(() => { if (bind()) bodyObserver.disconnect(); });
			bodyObserver.observe(document.body, {childList:true});
			window.setTimeout(() => bodyObserver.disconnect(), 12000);
		}
		const layoutObserver = new MutationObserver(scheduleAILauncherOffset);
		$$('.qil-modal').forEach(modal => layoutObserver.observe(modal, {attributes:true, attributeFilter:['hidden','aria-hidden']}));
		const cartPanel = document.querySelector('.cart-widget-side');
		if (cartPanel) layoutObserver.observe(cartPanel, {attributes:true, attributeFilter:['class','hidden','aria-hidden']});
		// Mobile browser chrome changes viewport height throughout a scroll;
		// CSS fixed positioning already follows it. Only a width change needs
		// a new slot measurement (rotation or crossing a breakpoint).
		let viewportWidth = window.innerWidth;
		window.addEventListener('resize', () => {
			if (window.innerWidth === viewportWidth) return;
			viewportWidth = window.innerWidth;
			scheduleAILauncherOffset();
		}, {passive:true});
		const toolbar = document.querySelector('.wd-toolbar');
		if (toolbar && typeof ResizeObserver === 'function') new ResizeObserver(scheduleAILauncherOffset).observe(toolbar);
	}

	function updateGoalLabels() {
		syncGoalFilters();
		const label = goalLabels()[state.goal];
		$$('[data-qil-selected-label], [data-qil-visual-goal]').forEach(el => { el.textContent = label; });
	}

	function productCorpus(product) {
		const terms = product?.match?.searchTokens || product?.searchTokens || [];
		return plain([product?.corpus, product?.name, product?.description, brandName(product), categoryNames(product).join(' '), Array.isArray(terms) ? terms.join(' ') : terms].filter(Boolean).join(' ')).toLowerCase();
	}

	function syncGoalFilters() {
		const hints = state.lang === 'ar' ? {
			stimfree:'منتجات موثقة بأنها خالية من المنبهات؛ خلو الكافيين وحده لا يكفي.',
			vegan:'منتجات موثقة بأنها مناسبة للنباتيين؛ نستبعد المعلومات المفقودة أو المتعارضة.',
			value:'من الأقل سعراً للحصة المدرجة. نحسب الخيارات المتوفرة فقط؛ أحجام الحصص قد تختلف.'
		} : {
			stimfree:'Explicitly documented stimulant-free products only. Caffeine-free alone is not sufficient.',
			vegan:'Explicitly documented vegan products only; missing or conflicting information is excluded.',
			value:'Lowest price per labelled serving first. In-stock options only; serving sizes may differ.'
		};
		$$('[data-pref]').forEach(button => {
			const key = button.dataset.pref, active = state.prefs.has(key);
			button.classList.toggle('is-active', active); button.setAttribute('aria-pressed', String(active));
			if (hints[key]) button.title = hints[key];
		});
	}

	function explicitDietary(product, key) {
		const dietary = product?.dietary || {};
		if (key === 'stimfree') return dietary.stimulantFree === true || dietary.stimulant_free === true;
		if (key === 'vegan') return dietary.vegan === true;
		return false;
	}

	// One immutable, versioned shortlist shared by the grid, routine and AI handoff.
	let recommendationContext = null;
	let goalController = null, goalRequestSerial = 0, goalLoadTimer = 0, goalInFlightKey = '';
	let goalResultIds = null, goalResultKey = '', goalHasMore = false, goalPage = 0;
	let goalPending = false, goalError = false, goalTotal = null, goalTouched = false;
	let goalPendingPage = 1, goalRetryPage = 1;
	const goalContextKey = () => JSON.stringify([state.goal, [...state.prefs].sort(), plain(state.search), state.lang, config.country, config.currency]);
	const goalPolicy = () => config.goalPolicy || {};
	function cancelGoalLoad() {
		window.clearTimeout(goalLoadTimer); goalLoadTimer = 0;
		goalController?.abort(); goalController = null; goalInFlightKey = ''; goalRequestSerial++;
	}
	function queueGoalPage() {
		if (!config.goalUrl || !$('[data-qil-results]')) return;
		window.clearTimeout(goalLoadTimer);
		const key = goalContextKey();
		goalPending = true; goalPendingPage = 1; goalRetryPage = 1; goalError = false; goalResultKey = key;
		// Show a loading state now. Only the accepted live response may paint cards.
		goalLoadTimer = window.setTimeout(() => {
			goalLoadTimer = 0;
			if (key === goalContextKey()) void loadGoalPage(1);
		}, 90);
	}
	function goalEvidence(product, goal = state.goal) {
		const policy = goalPolicy()[goal] || [];
		let best = null;
		for (const entry of product?.match?.evidence || []) {
			const priority = policy.indexOf(entry[0]), tier = Number(entry[1]);
			if (priority < 0 || !Number.isInteger(tier) || tier < 1 || tier > 4) continue;
			if (!best || tier > best.tier || (tier === best.tier && priority < best.priority)) {
				best = {purposeKey:entry[0], tier, priority, sources:[entry[2]], reasonCodes:[`taxonomy_${entry[2]}`]};
			}
		}
		return best;
	}
	function goalSignal(product, goal) { return goalEvidence(product, goal)?.tier || 0; }
	function productRejection(product, evidence) {
		if (!evidence) return 'no_taxonomy_evidence';
		if (state.prefs.has('stimfree') && !explicitDietary(product, 'stimfree')) return 'unverified_stimulant_free';
		if (state.prefs.has('vegan') && !explicitDietary(product, 'vegan')) return 'unverified_vegan';
		if (state.prefs.has('value') && (product?.price?.perServingVerified !== true || !Number.isFinite(Number(product?.price?.perServing)) || !(Number(product?.price?.perServing) > 0))) return 'missing_cost_per_serving';
		const query = plain(state.search).toLowerCase();
		if (query && !query.split(/\s+/).every(token => productCorpus(product).includes(token))) return 'search_mismatch';
		return '';
	}
	function rankTuple(product, evidence) {
		const completeness = (primaryImages(product).length ? 1 : 0) + (product?.facts?.servings ? 1 : 0) + (product?.facts?.primaryActives?.length ? 1 : 0);
		const available = product?.stock?.inStock === true ? 0 : 1, purchasable = product?.purchase?.purchasable === true ? 0 : 1;
		const leading = state.prefs.has('value')
			? [available, purchasable, Number(product.price.perServing), -evidence.tier, evidence.priority]
			: [-evidence.tier, evidence.priority, 0, available, purchasable];
		return [...leading, -completeness, -Number(product?.salesCount || 0), -Number(product?.rating || 0), Number(product.id)];
	}
	function freezeRecommendationContext(value) {
		if (value && typeof value === 'object' && !Object.isFrozen(value)) { Object.values(value).forEach(freezeRecommendationContext); Object.freeze(value); }
		return value;
	}
	function rebuildRecommendationContext() {
		const key = goalContextKey();
		if (goalResultKey && goalResultKey !== key) {
			cancelGoalLoad();
			goalResultIds = null; goalResultKey = ''; goalPage = 0; goalHasMore = false; goalTotal = null; goalPending = false;
		}
		const evidence = {}, rejected = {}, eligible = [];
		const candidates = goalResultIds === null ? products : goalResultIds.map(id => productIndex.get(String(id))).filter(Boolean);
		for (const product of candidates) {
			const itemEvidence = goalEvidence(product), reason = productRejection(product, itemEvidence);
			if (reason) { rejected[product.id] = reason; continue; }
			evidence[product.id] = itemEvidence;
			eligible.push({product, rank:rankTuple(product, itemEvidence)});
		}
		eligible.sort((a,b) => { for (let i=0;i<a.rank.length;i++) { const diff=a.rank[i]-b.rank[i]; if (diff) return diff; } return 0; });
		recommendationContext = freezeRecommendationContext({schemaVersion:'1.0', goal:state.goal, filters:[...state.prefs].sort(), query:plain(state.search),
			locale:state.lang, direction:state.lang === 'ar' ? 'rtl' : 'ltr', market:{country:config.country || '',currency:config.currency || ''},
			productIds:eligible.map(item => Number(item.product.id)), evidence, rejected, catalogueVersion:config.catalogueVersion || '', createdAt:new Date().toISOString()});
		window.QimiaRecommendationContext = recommendationContext;
		document.dispatchEvent(new CustomEvent('qimia:recommendation-context', {detail:recommendationContext}));
		return recommendationContext;
	}
	function recommendationSet() {
		const context = recommendationContext || rebuildRecommendationContext();
		return {products:context.productIds.map(id => productIndex.get(String(id))).filter(Boolean), fallback:'', context};
	}
	function invalidateRecommendations(clearComparison = true) {
		cancelGoalLoad();
		goalResultIds = null; goalResultKey = ''; goalPage = 0; goalHasMore = false; goalTotal = null; goalPending = false; goalError = false;
		recommendationContext = null;
		if (clearComparison) { state.compare = []; syncNativeCompareContext(); }
	}
	function refreshRecommendationSurfaces() {
		if (goalPending && goalPendingPage === 1) { updateGoalFeedback([]); return; }
		rebuildRecommendationContext();
		renderProducts(); renderCompareShowcase(); renderEditorialProducts(false); renderCompare(); syncCompareButtons();
	}
	function goalStatusText() {
		const count = recommendationContext?.productIds?.length || 0;
		if (goalPending) return state.lang === 'ar' ? 'جارٍ تجهيز المنتجات المطابقة والتحقق من توفرها…' : 'Preparing matching products and checking availability…';
		if (goalError) return state.lang === 'ar' ? 'تعذّر تحميل النتائج. أعد المحاولة.' : 'The results could not load. Please retry.';
		if (goalTotal !== null) {
			const names = state.lang === 'ar' ? {attribute:'خصائص المنتج',category:'فئة مباشرة',ancestor:'فئة رئيسية',tag:'وسم المنتج'} : {attribute:'product attributes',category:'direct category',ancestor:'parent category',tag:'product tag'};
			const reasons = [...new Set(Object.values(recommendationContext?.evidence || {}).flatMap(item => item.sources))].map(source => names[source]).filter(Boolean).join(' · ');
			return (state.lang === 'ar' ? `${count} من ${goalTotal} نتيجة مطابقة.` : `${count} of ${goalTotal} matching products.`) + (reasons ? (state.lang === 'ar' ? ' الأساس: ' : ' Matched by: ') + reasons : '');
		}
		return state.lang === 'ar' ? 'معاينة من فهرس الصفحة. اختر هدفاً للتحقق من الكتالوج الكامل.' : 'Preview from the page index. Select a goal to check the full catalogue.';
	}
	async function loadGoalPage(page = 1) {
		if (!config.goalUrl || !$('[data-qil-results]')) return;
		const key = goalContextKey(), requestKey = `${key}|${page}`;
		if (goalInFlightKey === requestKey) return;
		cancelGoalLoad();
		const serial = goalRequestSerial;
		const controller = typeof AbortController === 'function' ? new AbortController() : null;
		goalController = controller; goalInFlightKey = requestKey;
		goalPending = true; goalPendingPage = page; goalRetryPage = page; goalError = false; goalResultKey = key;
		updateGoalFeedback();
		let timedOut = false;
		const timeout = controller ? window.setTimeout(() => { timedOut = true; controller.abort(); }, 20000) : 0;
		try {
			const url = new URL(`${config.goalUrl.replace(/\/$/, '')}/${encodeURIComponent(state.goal)}`, location.href);
			if (url.origin !== location.origin) throw new Error('goal_origin');
			url.searchParams.set('qil_locale', state.lang); url.searchParams.set('filters', [...state.prefs].sort().join(','));
			url.searchParams.set('q', plain(state.search).slice(0,64)); url.searchParams.set('page', String(page));
			/* A logged-in frontend page and an authenticated REST request can bootstrap
			   WooCommerce customer geography in a different order. The currency engine
			   already treats an explicit storefront currency as the authoritative market
			   signal, so carry that exact selection into the live catalogue request. */
			const expectedCurrency = String(config.currency || '').toUpperCase();
			if (expectedCurrency) url.searchParams.set('qimia_currency', expectedCurrency);
			const goalHeaders = languageHeaders({'Accept':'application/json'});
			if (config.restNonce) goalHeaders['X-WP-Nonce'] = config.restNonce;
			const response = await fetch(url.href, {credentials:'same-origin', cache:'no-store', headers:goalHeaders, signal:controller?.signal});
			if (!response.ok) throw new Error('goal_http');
			const payload = await response.json();
			if (serial !== goalRequestSerial || key !== goalContextKey()) return;
			if (!Array.isArray(payload.products) || !payload.context || payload.context.goal !== state.goal || payload.context.locale !== state.lang) throw new Error('goal_contract');
			const responseCurrency = String(payload.context.market?.currency || '').toUpperCase();
			if (expectedCurrency && responseCurrency !== expectedCurrency) throw new Error('goal_currency');
			/* Do not reject a correct product payload because REST exposes a different
			   raw billing/shipping country than the rendered page. Currency is the real
			   storefront market owner here; schema/page still remain strict. */
			if (payload.context.schemaVersion !== '1.0' || Number(payload.page) !== page) throw new Error('goal_context');
			if (JSON.stringify([...(payload.context.filters || [])].sort()) !== JSON.stringify([...state.prefs].sort()) || String(payload.context.query || '') !== plain(state.search).slice(0,64)) throw new Error('goal_filters');
			if (page > 1 && String(payload.catalogueVersion || '') !== String(config.catalogueVersion || '')) { invalidateRecommendations(false); goalResultIds = []; goalError = true; goalRetryPage = 1; refreshRecommendationSurfaces(); return; }
			if (payload.products.some(product => expectedCurrency && String(product?.price?.currency || '').toUpperCase() !== expectedCurrency)) throw new Error('goal_product_currency');
			const accepted = upsertProducts(payload.products);
			const allowed = new Set((payload.context.productIds || []).map(Number));
			const ids = accepted.filter(product => allowed.has(Number(product.id)) && goalEvidence(product)).map(product => Number(product.id));
			goalResultIds = page === 1 ? ids : [...new Set([...(goalResultIds || []), ...ids])];
			goalPage = page; goalHasMore = payload.hasMore === true; goalTotal = Number(payload.total) || 0;
			config.catalogueVersion = payload.catalogueVersion || config.catalogueVersion;
			goalPending = false; refreshRecommendationSurfaces();
		} catch (error) {
			if (serial !== goalRequestSerial || (error?.name === 'AbortError' && !timedOut)) return;
			goalPending = false; goalError = true;
			if (page === 1) { goalResultIds = []; recommendationContext = null; renderProducts(); }
			else updateGoalFeedback();
		} finally {
			window.clearTimeout(timeout);
			if (serial === goalRequestSerial) { goalController = null; goalInFlightKey = ''; }
		}
	}

	function primaryImages(product) {
		if (Array.isArray(product?.images) && product.images.length) return product.images;
		if (product?.image) return [{src:product.image, alt:product.name || '', width:600, height:600}];
		return [];
	}

	function imageMarkup(product) {
		const images = primaryImages(product);
		if (!images.length) return '<div class="qil-product-placeholder"><span>Q</span><small>QIMIA</small></div>';
		const make = image => {
			const src = escapeHtml(safeUrl(image.src || image.url));
			const srcset = image.srcset ? ` srcset="${escapeHtml(image.srcset)}"` : '';
			const sizes = image.sizes ? ` sizes="${escapeHtml(image.sizes)}"` : ' sizes="(max-width: 680px) 46vw, (max-width: 1100px) 44vw, 390px"';
			const width = Math.max(1, Number(image.width || 600)), height = Math.max(1, Number(image.height || 600));
			return `<img class="qil-image-primary" src="${src}"${srcset}${sizes} width="${width}" height="${height}" alt="${escapeHtml(image.alt || product.name || '')}" loading="lazy" decoding="async" style="transform:none;transition:none;animation:none">`;
		};
		return make(images[0]);
	}

	function factValue(product, names) {
		const facts = product?.facts || {};
		for (const name of names) {
			const value = facts[name];
			if (value === 0) return '0';
			if ((typeof value === 'string' || typeof value === 'number') && plain(value)) return localizeFactValue(value);
			if (value && typeof value === 'object') { const formatted = plain(value.formatted || value.value || [value.name, value.amount].filter(Boolean).join(' ')); if (formatted) return localizeFactValue(formatted); }
		}
		return '';
	}

	function normalizedFactKey(value) {
		return plain(value).toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
	}

	function typedFactAmount(product, key) {
		const facts = product?.facts || {};
		const direct = facts[key];
		if (direct && typeof direct === 'object') {
			if (key === 'caffeine' && direct.state === 'unknown') return '';
			const amount = plain(direct.amount || direct.value || direct.formatted || direct.label);
			if (amount) return localizeFactValue(amount);
		}
		if (typeof direct === 'string' || typeof direct === 'number') {
			const amount = plain(direct);
			if (amount) return localizeFactValue(amount);
		}
		const legacy = facts[`${key}G`];
		if (typeof legacy === 'number' && Number.isFinite(legacy)) return `${legacy} g`;
		if (typeof legacy === 'string' && plain(legacy)) return localizeFactValue(legacy);
		const active = (Array.isArray(facts.primaryActives) ? facts.primaryActives : []).find(item => {
			const activeKey = normalizedFactKey(item?.key || item?.name);
			return activeKey === normalizedFactKey(key);
		});
		return active ? localizeFactValue(active.amount || active.value || '') : '';
	}

	function typedFactDisplay(product, key) {
		const facts = product?.facts || {};
		const direct = facts[key];
		if (direct && typeof direct === 'object') {
			if (key === 'caffeine' && direct.state === 'unknown') return '';
			const display = plain(direct.formatted || direct.label || [direct.name, direct.amount].filter(Boolean).join(' '));
			if (display) return localizeFactValue(display);
		}
		const active = (Array.isArray(facts.primaryActives) ? facts.primaryActives : []).find(item => {
			const activeKey = normalizedFactKey(item?.key || item?.name);
			return activeKey === normalizedFactKey(key);
		});
		if (active) return localizeFactValue(active.formatted || active.label || [active.name, active.amount].filter(Boolean).join(' '));
		return typedFactAmount(product, key);
	}

	function primaryActiveValue(product, preferredKeys = [], allowFallback = true) {
		const actives = Array.isArray(product?.facts?.primaryActives) ? product.facts.primaryActives : [];
		const rows = actives.map(item => ({
			key: normalizedFactKey(item?.key || item?.name),
			value: localizeFactValue(typeof item === 'string' ? item : (item?.formatted || item?.label || [item?.name, item?.amount].filter(Boolean).join(' ')))
		})).filter(item => item.value);
		for (const preferred of preferredKeys.map(normalizedFactKey)) {
			const match = rows.find(item => item.key && (item.key === preferred || item.key.includes(preferred) || preferred.includes(item.key)));
			if (match) return match.value;
		}
		return allowFallback ? (rows[0]?.value || '') : '';
	}

	function activeValue(product) {
		const purpose = productPurposeKey(product);
		const typedOrder = {
			protein: ['protein'], mass_gainer: ['protein'], creatine: ['creatine'],
			pre_workout: ['caffeine'], beauty_support: ['collagen']
		}[purpose] || [];
		for (const key of typedOrder) {
			const value = typedFactDisplay(product, key);
			if (value) return value;
		}
		const preferredActives = {
			protein: ['protein'], mass_gainer: ['protein'], creatine: ['creatine'],
			pre_workout: ['caffeine', 'l_citrulline', 'citrulline', 'beta_alanine'],
			amino_recovery: ['bcaa', 'eaa', 'glutamine', 'amino_acid'],
			hydration: ['electrolyte', 'sodium', 'potassium'],
			joint_support: ['glucosamine', 'chondroitin', 'msm'],
			sleep_support: ['melatonin', 'magnesium', 'gaba'],
			beauty_support: ['collagen', 'biotin'],
			omega_support: ['omega_3', 'epa', 'dha']
		}[purpose] || [];
		const strictTypedPurpose = ['protein', 'mass_gainer', 'creatine'].includes(purpose);
		return primaryActiveValue(product, preferredActives, !strictTypedPurpose) || (!strictTypedPurpose ? factValue(product, ['primaryActive']) : '');
	}

	function cardFacts(product) {
		const purpose = productPurposeKey(product), facts = [];
		const servings = factValue(product, ['servingsPerContainer','servings']) || plain(product.servings);
		const servingSize = factValue(product, ['servingSize']);
		const caffeine = typedFactAmount(product, 'caffeine') || factValue(product, ['caffeineMgTotal']);
		const protein = typedFactAmount(product, 'protein');
		const creatine = typedFactAmount(product, 'creatine');
		const collagen = typedFactAmount(product, 'collagen');
		const active = activeValue(product);
		const add = (label, value) => { if (plain(value) && !facts.some(item => item.value === plain(value))) facts.push({label, value:plain(value)}); };
		if (purpose === 'pre_workout' && caffeine) add(t('caffeineFact'), caffeine);
		else if ((purpose === 'protein' || purpose === 'mass_gainer') && protein) add(t('protein'), protein);
		else if (purpose === 'creatine' && creatine) add(t('creatine'), creatine);
		else if (purpose === 'beauty_support' && collagen) add(t('collagen'), collagen);
		else add(t('keyActive'), active);
		add(t('servings'), servings);
		if (facts.length < 2) add(t('servingSize'), servingSize);
		if (facts.length < 2 && caffeine) add(t('caffeineFact'), /mg/i.test(caffeine) ? caffeine : `${caffeine} mg`);
		while (facts.length < 2) facts.push({label:facts.length ? t('servingSize') : t('keyActive'), value:t('notListed')});
		return facts.slice(0,2);
	}

	function productPurposeKey(product) { return plain(product?.match?.purposeKey || ''); }

	function productPurpose(product) {
		const keys = {
			protein:'purposeProtein', creatine:'purposeCreatine', mass_gainer:'purposeMassGainer',
			pre_workout:'purposePreWorkout', fat_burner:'purposeFatBurner', amino_recovery:'purposeAminoRecovery',
			hydration:'purposeHydration', joint_support:'purposeJointSupport', sleep_support:'purposeSleepSupport',
			beauty_support:'purposeBeautySupport', omega_support:'purposeOmegaSupport', daily_wellness:'purposeDailyWellness',
			goal_support:'purposeGoalSupport'
		};
		return t(keys[productPurposeKey(product)] || 'purposeGoalSupport');
	}

	function stockLabel(product) {
		if (state.lang !== 'ar' && plain(product?.stock?.label)) return plain(product.stock.label);
		if (product?.stock?.status === 'outofstock' || product?.inStock === false) return t('outOfStock');
		return t('inStock');
	}

	/* Exact-item repurchase. Private data stays in memory, never localStorage,
	   the public catalogue cache, AI context, marketing events or cookies. */
	const repeatPurchases = new Map(), inventoryLive = new Map(), inventorySeen = new Set();
	const repeatBusy = new Set(), repeatRequests = new Map();
	let repeatNonce = '', repeatProducts = [], repeatLoaded = false, repeatLoadSerial = 0;
	let repeatController = null, repeatObserver = null, repeatRefreshTimer = 0;
	let repeatRefreshing = false;
	let inventoryClockOffset = 0, inventoryExpiryTimer = 0;
	const inventoryNow = () => Math.floor((Date.now() + inventoryClockOffset) / 1000);
	function newestInventory(id, fallback = null) {
		const live = inventoryLive.get(String(id));
		return !live || (fallback && Number(fallback.checkedAt) > Number(live.checkedAt)) ? fallback : live;
	}

	function actionProduct(id) { return productIndex.get(String(id)) || repeatProducts.find(product => String(product.id) === String(id)); }
	function repeatRow(key) { return repeatPurchases.get(String(key || '')) || null; }
	function repeatText(key) {
		const labels = {
			buy: ['Buy again', 'اشترِ مجدداً'], chosen: ['Your previous selection', 'خيارك السابق'],
			change: ['Change options', 'تغيير الخيارات'], unavailable: ['Your previous option is unavailable', 'خيارك السابق غير متوفر'],
			loading: ['Adding…', 'جارٍ الإضافة…'], failed: ['Could not confirm the addition. Check your cart before trying again.', 'تعذّر تأكيد الإضافة. راجع السلة قبل المحاولة مرة أخرى.'],
			new: ['New Arrival', 'وصل حديثاً'], restocked: ['Back in Stock', 'متوفر من جديد']
		};
		return (labels[key] || [key, key])[state.lang === 'ar' ? 1 : 0];
	}
	function inventoryMarkup(data) {
		if (!data || data.inStock !== true) return '';
		const now = inventoryNow(), pieces = [];
		if (data.newArrival === true && Number(data.newUntil) > now) pieces.push(`<span class="qil-inventory-badge is-new" data-qil-inventory-until="${Number(data.newUntil)}">${escapeHtml(repeatText('new'))}</span>`);
		if (data.backInStock === true && Number(data.restockUntil) > now) pieces.push(`<span class="qil-inventory-badge is-restocked" data-qil-inventory-until="${Number(data.restockUntil)}">${escapeHtml(repeatText('restocked'))}</span>`);
		return pieces.join('');
	}
	function repeatButton(product, key) {
		const row = repeatRow(key);
		if (!row?.canAdd || !repeatNonce) return '';
		const id = Number(product.id), busy = repeatBusy.has(key);
		// Same qil-buy class/skin as the original cards; only this new rail owns
		// the exact-item action. Do not attach native Woo AJAX classes to it.
		return `<button type="button" class="qil-buy qil-repeat-buy${busy ? ' loading' : ''}" data-qil-repeat-buy="${escapeHtml(key)}" data-qil-parent-id="${id}" data-product_id="${Number(row.variationId || id)}" data-quantity="1" aria-label="${escapeHtml(`${repeatText('buy')}: ${plain(product.name || '')}${row.selection ? ' — ' + row.selection : ''}`)}" ${busy ? 'disabled aria-busy="true"' : ''}><span>${escapeHtml(repeatText(busy ? 'loading' : 'buy'))}</span><svg aria-hidden="true"><use href="#qil-i-cart-plus"/></svg></button>`;
	}
	function repeatNote(product, key) {
		const row = repeatRow(key);
		if (!row?.canAdd) return '';
		return `<div class="qil-repeat-note"><span>${escapeHtml(repeatText('chosen'))}${row.selection ? `: <strong dir="auto">${escapeHtml(row.selection)}</strong>` : ''}</span></div>`;
	}
	function repeatCardProduct(product, key) {
		const row = repeatRow(key);
		if (!row?.canAdd) return product;
		return { ...product, exactRepeatSelection: true, price: row.price, inventory: row.inventory,
			images: row.image ? [row.image] : product.images,
			promotion: row.variationId ? { isFlash: false } : product.promotion };
	}
	function refreshRepeatCards() {
        if (config.personalizationEnabled) return;
		// Never replace, relabel or reprice an existing catalogue/native/PDP card.
		const records = new Map(repeatProducts.map(product => [String(product.id), product]));
		const cards = [];
		if (repeatNonce) repeatPurchases.forEach((row, key) => {
			const product = records.get(String(row.productId));
			if (product && row.canAdd === true && cards.length < 8) cards.push({product, key});
		});
		document.querySelectorAll('[data-qil-repeat-section]').forEach(section => {
			const grid = section.querySelector('[data-qil-repeat-grid]');
			if (!grid) return;
			grid.innerHTML = cards.map(({product, key}, index) => productCard(product, index, 'buy-again', key)).join('');
			section.hidden = cards.length === 0;
		});
		setupRails(); observeReveals();
	}
	function repeatVisibleIds(onlyUnseen = false) {
		const ids = [...document.querySelectorAll('.qil-product-card[data-product-id],[data-qil-native-inventory]')].map(n => Number(n.dataset.productId || n.dataset.qilNativeInventory));
		// A loop template can omit the badge hook. Its native purchase button still supplies a real ID.
		document.querySelectorAll('.wd-product a[data-product_id],li.product a[data-product_id]').forEach(n => ids.push(Number(n.dataset.product_id)));
		return [...new Set(ids)].filter(id => Number.isSafeInteger(id) && id > 0 && (!onlyUnseen || !inventorySeen.has(String(id)))).slice(0, 64);
	}
	function repeatEndpoint(name) { return wcAjaxEndpoint(name); }
	// Public inventory labels are the same for every shopper. A short per-tab
	// copy means moving between pages does not ask the server again for
	// products it has just checked. Account data is never stored here.
	// One copy per currency and market: availability can differ by destination.
	const INVENTORY_STORE = `qil_inventory_v2:${String(config.currency || '')}:${String(config.country || '')}`, INVENTORY_STORE_TTL = 120000;
	function inventoryStoreRead() {
		try { const store = JSON.parse(window.sessionStorage.getItem(INVENTORY_STORE) || '{}'); return store && typeof store === 'object' ? store : {}; } catch (_) { return {}; }
	}
	function inventoryStoreWrite(entries) {
		try {
			const store = inventoryStoreRead(), now = Date.now();
			Object.keys(store).forEach(id => { if (!(now - Number(store[id]?.at) < INVENTORY_STORE_TTL)) delete store[id]; });
			Object.entries(entries || {}).forEach(([id, value]) => { if (value && typeof value === 'object') store[id] = {at:now, value}; });
			const ids = Object.keys(store);
			if (ids.length > 400) ids.sort((a, b) => store[a].at - store[b].at).slice(0, ids.length - 400).forEach(id => delete store[id]);
			window.sessionStorage.setItem(INVENTORY_STORE, JSON.stringify(store));
		} catch (_) { /* Storage refusal only means the next page asks again. */ }
	}
	function applyStoredInventory(ids) {
		const store = inventoryStoreRead(), now = Date.now();
		return ids.filter(id => {
			const row = store[String(id)];
			if (!row || !(now - Number(row.at) < INVENTORY_STORE_TTL) || !row.value || typeof row.value !== 'object') return true;
			inventoryLive.set(String(id), row.value); inventorySeen.add(String(id));
			return false;
		});
	}
	function renderLiveInventory() {
		document.querySelectorAll('.qil-product-card[data-product-id]').forEach(card => {
			const id = String(card.dataset.productId), row = repeatRow(card.dataset.qilRepeatKey);
			const data = row?.canAdd ? newestInventory(row.variationId || row.productId, row.inventory) : newestInventory(id, productIndex.get(id)?.inventory);
			const holder = card.querySelector('[data-qil-inventory-labels]');
			if (holder && data) {
				const html = inventoryMarkup(data); if (holder.innerHTML !== html) holder.innerHTML = html;
			}
		});
		document.querySelectorAll('[data-qil-native-inventory]').forEach(holder => {
			// Native-loop labels always describe the public product, not an account's flavour.
			const data = inventoryLive.get(String(holder.dataset.qilNativeInventory));
			if (data) { const html = inventoryMarkup(data); if (holder.innerHTML !== html) holder.innerHTML = html; }
		});
		// One deadline timer (not polling) also expires cached/native HTML labels.
		window.clearTimeout(inventoryExpiryTimer);
		let next = Infinity;
		document.querySelectorAll('[data-qil-inventory-until]').forEach(badge => {
			const until = Number(badge.dataset.qilInventoryUntil);
			if (!Number.isFinite(until) || until <= inventoryNow()) badge.remove();
			else next = Math.min(next, until);
		});
		if (Number.isFinite(next) && document.visibilityState !== 'hidden') {
			inventoryExpiryTimer = window.setTimeout(renderLiveInventory, Math.min(2147480000, Math.max(100, next * 1000 - Date.now() - inventoryClockOffset + 50)));
		}
		positionNativeInventory();
	}
	function positionNativeInventory() {
		// The Woo hook can sit outside a theme's thumbnail wrapper. Anchor ONLY
		// the new label to that existing image area; never touch purchase controls.
		document.querySelectorAll('[data-qil-native-inventory]').forEach(holder => {
			holder.dir = state.lang === 'ar' ? 'rtl' : 'ltr';
			if (holder.parentElement?.classList.contains('qil-inventory-anchor')) return;
			const card = holder.closest('.wd-product,li.product');
			if (!card) return;
			const imageArea = card.querySelector('.product-element-top,.wd-product-element-top,.woocommerce-loop-product__thumbnail');
			if (imageArea && !imageArea.contains(holder)) imageArea.appendChild(holder);
			if (imageArea) { imageArea.classList.add('qil-inventory-anchor'); return; }
			const image = card.querySelector('img.attachment-woocommerce_thumbnail,img.wp-post-image');
			if (!image || !image.parentElement) return;
			let anchor = image.closest('.qil-inventory-anchor');
			if (!anchor) {
				anchor = document.createElement('span'); anchor.className = 'qil-inventory-anchor qil-inventory-image-wrap';
				const visual = image.parentElement.tagName === 'PICTURE' ? image.parentElement : image;
				visual.before(anchor); anchor.appendChild(visual);
			}
			anchor.appendChild(holder);
		});
	}
	function clearRepeatPrivate() {
		repeatLoadSerial++; repeatController?.abort(); repeatNonce = ''; repeatProducts = []; repeatLoaded = false;
		repeatPurchases.clear(); repeatBusy.clear(); repeatRequests.clear();
		document.querySelectorAll('[data-qil-repeat-section]').forEach(section => {
			section.hidden = true; section.querySelector('[data-qil-repeat-grid]')?.replaceChildren();
			const status = section.querySelector('[data-qil-repeat-status]'); if (status) status.textContent = '';
		});
	}
	// Search/preview crawlers get the inventory labels already in the page;
	// a live refresh per crawled URL is a full PHP request with no benefit.
	var automatedAgent = /googlebot|googleother|google-inspectiontool|adsbot-google|bingbot|bingpreview|applebot|yandex|baiduspider|duckduckbot|ahrefsbot|semrushbot|petalbot|bytespider|gptbot|oai-searchbot|chatgpt-user|claudebot|facebookexternalhit|meta-externalagent/i.test(navigator.userAgent || '');
	async function loadRepeatContext(inventoryOnly = false) {
        if (config.personalizationEnabled) inventoryOnly = true;
		if (config.repeatPurchaseEnabled !== true || document.visibilityState === 'hidden' || automatedAgent) return;
		const endpoint = repeatEndpoint('qil_repeat_context'); if (!endpoint) return;
		// One request at a time. The active request already represents the newest
		// account context; never queue a second PHP request behind it.
		if (repeatRefreshing) return;
		let ids = inventoryOnly ? repeatVisibleIds(true) : [];
		if (inventoryOnly && ids.length) ids = applyStoredInventory(ids);
		if (inventoryOnly && !ids.length) { renderLiveInventory(); return; }
		const serial = ++repeatLoadSerial;
		repeatController?.abort(); repeatController = new AbortController(); repeatRefreshing = true;
        const timeout = window.setTimeout(() => repeatController?.abort(), 12000);
		try {
			// Full Buy Again reads already contain the exact purchased item's live
			// inventory. Do not make the server also re-check every product rendered
			// on the page. Public loop badges are server-rendered with the page.
			const body = new URLSearchParams({locale: state.lang});
			if (inventoryOnly) { body.set('inventory_only', '1'); body.set('products', ids.join(',')); }
			const response = await fetch(endpoint, {method:'POST', credentials:'same-origin', cache:'no-store', signal:repeatController.signal,
				headers: languageHeaders({Accept:'application/json','Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'}), body});
			if (!response.ok) throw new Error('repeat_context');
			const data = await response.json();
			if (serial !== repeatLoadSerial) return;
			if (String(data.currency || '').toUpperCase() !== String(config.currency || '').toUpperCase()) throw new Error('repeat_currency');
			if (Number.isFinite(Number(data.serverTime)) && Number(data.serverTime) > 0) inventoryClockOffset = Number(data.serverTime) * 1000 - Date.now();
			Object.entries(data.inventory || {}).forEach(([id, value]) => { inventoryLive.set(id, value); inventorySeen.add(id); });
			inventoryStoreWrite(data.inventory);
			if (!inventoryOnly) {
				repeatPurchases.clear();
				repeatNonce = data.authenticated === true ? String(data.nonce || '') : '';
				if (repeatNonce) for (const row of (Array.isArray(data.purchases) ? data.purchases : []).slice(0,24)) {
					if (Number(row.productId) > 0 && Number(row.orderId) > 0 && Number(row.itemId) > 0 && row.canAdd === true) {
						repeatPurchases.set(`${Number(row.orderId)}:${Number(row.itemId)}`, row);
					}
				}
				repeatProducts = repeatNonce ? (Array.isArray(data.products) ? data.products : []).slice(0,8) : [];
				repeatLoaded = true; refreshRepeatCards();
			}
			renderLiveInventory();
		} catch (error) {
			// Failure leaves the existing Add to cart / options flow intact.
			if (!inventoryOnly && serial === repeatLoadSerial) clearRepeatPrivate();
		} finally {
            window.clearTimeout(timeout);
			repeatRefreshing = false;
		}
	}
	function scheduleRepeatInventory() {
		// Product cards inserted by filters, rails or Buy Again already carry
		// server/public inventory. Reposition/re-render locally instead of firing
		// another wc-ajax request 180ms after every DOM mutation.
		clearTimeout(repeatRefreshTimer);
		repeatRefreshTimer = window.setTimeout(renderLiveInventory, 180);
	}
	function repeatSetBusy(id, busy) {
		if (busy) repeatBusy.add(id); else repeatBusy.delete(id);
		document.querySelectorAll('[data-qil-repeat-buy]').forEach(button => {
			if (button.dataset.qilRepeatBuy !== id) return;
			button.disabled = busy; button.setAttribute('aria-busy', String(busy)); button.classList.toggle('loading', busy);
			const label = button.querySelector('span'); if (label) label.textContent = repeatText(busy ? 'loading' : 'buy');
		});
	}
	const personalizationOwnsRepeatContext = () => config.personalizationEnabled === true && !!document.querySelector('[data-qil-personal-anchor]');
	async function addRepeatItem(button) {
		const id = String(button.dataset.qilRepeatBuy || ''), row = repeatPurchases.get(id);
		if (!row?.canAdd || !repeatNonce || repeatBusy.has(id)) return;
		const endpoint = repeatEndpoint('qil_buy_again'); if (!endpoint) return;
		repeatSetBusy(id, true);
		// Reuse the same key after an ambiguous network failure. Do not silently
		// issue another add request, and never retry a cart mutation automatically.
		let requestId = repeatRequests.get(id);
		if (!requestId) { requestId = window.crypto?.randomUUID?.() || `${Date.now().toString(36)}_${Math.random().toString(36).slice(2)}_${Math.random().toString(36).slice(2)}`; repeatRequests.set(id, requestId); }
        const controller = new AbortController();
        const timeout = window.setTimeout(() => controller.abort(), 20000);
		try {
			const body = new URLSearchParams({nonce:repeatNonce, order_id:String(row.orderId), item_id:String(row.itemId), locale:state.lang, request_id:requestId});
            const presentation = button.closest('[data-qil-presentation]')?.dataset.qilPresentation;
            if (presentation) body.set('qil_presentation', presentation);
			const response = await fetch(endpoint, {method:'POST', credentials:'same-origin', cache:'no-store',
				headers:languageHeaders({Accept:'application/json','Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'}), signal:controller.signal, body});
			const data = await response.json();
			if (!response.ok || data.error) {
				if (['login', 'nonce', 'unavailable'].includes(data.code)) repeatRequests.delete(id);
				if (data.code === 'login' || data.code === 'nonce') clearRepeatPrivate();
                else if (data.code === 'unavailable') {
                    inventorySeen.clear();
                    if (personalizationOwnsRepeatContext()) window.QILPersonalization?.refresh();
                    else void loadRepeatContext();
                }
				throw new Error(plain(data.message || repeatText('failed')));
			}
			repeatRequests.delete(id);
			if (window.jQuery) window.jQuery(document.body).trigger('added_to_cart', [data.fragments, data.cart_hash, window.jQuery(button)]);
			else registerCartSuccess(null, data.fragments, data.cart_hash, button);
			window.QimiaShoppingContext?.select?.(Number(row.productId), Number(row.variationId || 0));
			const status = button.closest('[data-qil-repeat-section]')?.querySelector('[data-qil-repeat-status]'); if (status) status.textContent = t('cartAdded');
		} catch (error) {
			const message = error?.name !== 'AbortError' && error?.message && !/^Failed to fetch|^Load failed|^NetworkError|^Unexpected/.test(error.message) ? error.message : repeatText('failed');
			showToast(message);
			const status = button.closest('[data-qil-repeat-section]')?.querySelector('[data-qil-repeat-status]'); if (status) status.textContent = message;
		} finally { window.clearTimeout(timeout); repeatSetBusy(id, false); }
	}
	function setupRepeatPurchase() {
		if (config.repeatPurchaseEnabled !== true) return;
		renderLiveInventory();

		// Shop/category/search/brand pages have no Buy Again surface. Their public
		// inventory badges are rendered by Woo with the page, so a private PHP
		// request here only duplicates work and was the main browsing CPU spike.
		if (!document.querySelector('[data-qil-repeat-section]')) return;

		// Unified personalization owns this surface and already returns Buy Again +
		// inventory in one response. Never stack qil_repeat_context beside it.
		if (personalizationOwnsRepeatContext()) return;

		// The legacy Buy Again rail is account-only. restNonce is emitted only on a
		// signed-in frontend response, so guests do not spend PHP merely to receive
		// an empty authenticated=false payload.
		const signedInPage = typeof config.restNonce === 'string' && config.restNonce.length > 0;
		if (!signedInPage) return;

		document.addEventListener('click', event => {
			const target = event.target instanceof Element ? event.target : null;
			if (target?.closest('a[href*="customer-logout"],a[href*="action=logout"]')) { clearRepeatPrivate(); return; }
			const button = target?.closest('[data-qil-repeat-section] [data-qil-repeat-buy]'); if (!button) return;
			event.preventDefault(); event.stopImmediatePropagation(); void addRepeatItem(button);
		}, true);
		repeatObserver = new MutationObserver(records => {
			if (records.some(record => Array.from(record.addedNodes).some(node => node instanceof Element && (node.matches('.qil-product-card,.wd-product,li.product') || node.querySelector('.qil-product-card,.wd-product,li.product'))))) scheduleRepeatInventory();
		});
		repeatObserver.observe(document.body, {childList:true, subtree:true});
		window.addEventListener('pagehide', clearRepeatPrivate);
		window.addEventListener('pageshow', event => {
			if (!event.persisted) return;
			inventorySeen.clear();
			void loadRepeatContext();
		});
		let lastVisible = Date.now();
		document.addEventListener('visibilitychange', () => {
			if (document.visibilityState === 'hidden') { lastVisible = Date.now(); return; }
			if (Date.now() - lastVisible > 60000) {
				inventorySeen.clear();
				void loadRepeatContext();
			} else renderLiveInventory();
		});

		// Account history is not needed for first paint. Start it in idle time so
		// real product/category rendering and add-to-cart work always win CPU first.
		const startRepeat = () => { if (document.visibilityState !== 'hidden') void loadRepeatContext(); };
		if ('requestIdleCallback' in window) window.requestIdleCallback(startRepeat, {timeout:1200});
		else window.setTimeout(startRepeat, 350);
	}

	function purchaseButton(product) {
		const purchase = product?.purchase || {}, action = purchase.action || (product?.type === 'simple' ? 'add' : 'view');
		const url = safeUrl(purchase.url || product?.cartUrl || product?.url), id = escapeHtml(product.id);
		if (action === 'add') {
			const label = t('addToBag');
			const description = `${label}: ${plain(product?.name || '')}`;
			const sku = escapeHtml(purchase.sku || '');
			if (purchase.ajax === true) {
				return `<a class="qil-buy button product_type_simple add_to_cart_button ajax_add_to_cart add-to-cart-loop" href="${escapeHtml(url)}" data-product_id="${id}" data-product_sku="${sku}" data-quantity="1" data-mobile-label="${escapeHtml(t('addMobile'))}" role="button" rel="nofollow" aria-label="${escapeHtml(description)}" title="${escapeHtml(description)}"><span>${escapeHtml(label)}</span><svg aria-hidden="true"><use href="#qil-i-cart-plus"/></svg></a>`;
			}
			return `<a class="qil-buy" href="${escapeHtml(url)}" data-mobile-label="${escapeHtml(t('addMobile'))}" rel="nofollow"><span>${escapeHtml(label)}</span><svg aria-hidden="true"><use href="#qil-i-cart-plus"/></svg></a>`;
		}
		if (action === 'select') {
			return `<a class="qil-buy qil-buy-secondary qil-select-options" href="${escapeHtml(url)}" data-qil-quick-view-id="${id}" data-mobile-label="${escapeHtml(t('optionsMobile'))}" aria-haspopup="dialog" aria-controls="qil-quick-view-modal"><span>${escapeHtml(t('selectOptions'))}</span><svg aria-hidden="true"><use href="#qil-i-cart-plus"/></svg></a>`;
		}
		return `<a class="qil-buy qil-buy-secondary" href="${escapeHtml(url)}"><span>${escapeHtml(t('details'))}</span><svg aria-hidden="true"><use href="#qil-i-arrow"/></svg></a>`;
	}

	function productCard(product, index, collectionKey = '', repeatKey = '') {
		const repeat = collectionKey === 'buy-again' && repeatRow(repeatKey)?.canAdd === true && !!repeatNonce;
		if (repeat) product = repeatCardProduct(product, repeatKey);
		const selected = state.compare.some(item => String(item.id) === String(product.id));
		let role = index === 0 ? t('foundationPick') : index === 1 ? t('targetedPick') : t('optionalPick');
		if (collectionKey === 'buy-again') role = repeatText('buy');
        if (collectionKey === 'personal-discover') role = state.lang === 'ar' ? 'من المتجر' : 'Store selection';
        if (collectionKey === 'personal-recent') role = state.lang === 'ar' ? 'شاهدتها مؤخراً' : 'Recently viewed';
        if (collectionKey === 'personal-related' || collectionKey === 'personal-cart') role = state.lang === 'ar' ? 'اختيارات مرتبطة' : 'Related selections';
        if (collectionKey === 'protein') role = t('proteinPick');
		if (collectionKey === 'creatine') role = t('creatinePick');
		if ((collectionKey === 'best-sellers' || collectionKey === 'weekly-best-sellers') && Number(product?.salesCount || 0) > 0) role = t('bestSeller');
		const facts = cardFacts(product), salePercent = Number(product?.price?.savingsPct || 0), promotion = product?.promotion || {}, verifiedBadges = [];
		const promotionKind = promotion.isFlash === true ? 'flash' : (hasVisibleSale(product) ? 'sale' : 'none');
		const percentMark = state.lang === 'ar' ? '٪' : '%';
		const saleBadge = promotion.isFlash === true
			? `<span class="qil-sale-badge qil-flash-sale-badge" data-qil-sale-badge="flash">${escapeHtml(t('flashSale'))}${Number(promotion.discountPct || 0) > 0 ? ` · −${Math.round(Number(promotion.discountPct))}${percentMark}` : ''}</span>`
			: (hasVisibleSale(product) && salePercent > 0 ? `<span class="qil-sale-badge" data-qil-sale-badge="sale">−${Math.round(salePercent)}${percentMark}</span>` : '');
		const labelStack = `<span class="qil-label-stack${saleBadge ? ' has-multiple' : ''}"><span class="qil-product-badge">${escapeHtml(role)}</span>${saleBadge}</span>`;
		if (product?.dietary?.vegan === true) verifiedBadges.push(state.lang === 'ar' ? 'نباتي' : 'Vegan');
		if (product?.dietary?.halal === true) verifiedBadges.push(state.lang === 'ar' ? 'حلال' : 'Halal');
		return `<article class="qil-product-card qil-reveal" data-product-id="${escapeHtml(product.id)}" data-qil-promotion="${promotionKind}"${repeat ? ` data-qil-repeat-rail-card data-qil-repeat-key="${escapeHtml(repeatKey)}"` : ''}>
			<a class="qil-product-image" href="${escapeHtml(safeUrl(product.url))}" aria-label="${escapeHtml(product.name)}">${imageMarkup(product)}${labelStack}<span class="qil-inventory-labels" data-qil-inventory-labels>${inventoryMarkup(repeat ? product.inventory : newestInventory(product.id, product.inventory))}</span>${verifiedBadges.length ? `<span class="qil-verified-badge"><svg><use href="#qil-i-check"/></svg>${escapeHtml(verifiedBadges[0])}</span>` : ''}</a>
			<div class="qil-product-info"><div class="qil-product-brand"><span>${escapeHtml(brandName(product))}</span><span><i></i>${escapeHtml(stockLabel(product))}</span></div><h3><a href="${escapeHtml(safeUrl(product.url))}">${escapeHtml(product.name || '')}</a></h3><div class="qil-card-price">${priceMarkup(product)}${perServingMarkup(product)}</div><div class="qil-facts">${facts.map(item => `<div class="qil-fact"><small class="qil-fact-label">${escapeHtml(item.label)}</small><strong class="qil-fact-value" dir="auto" title="${escapeHtml(item.value)}">${escapeHtml(item.value)}</strong></div>`).join('')}</div>${repeat ? repeatNote(product, repeatKey) : ''}<div class="qil-card-actions">${repeat ? repeatButton(product, repeatKey) : purchaseButton(product)}<button class="qil-compare-add${selected ? ' is-active' : ''}" type="button" data-compare-id="${escapeHtml(product.id)}" aria-pressed="${selected ? 'true' : 'false'}" aria-label="${escapeHtml(selected ? t('removeCompare') : t('addCompare'))}" title="${escapeHtml(selected ? t('removeCompare') : t('addCompare'))}"><svg class="qil-compare-icon" aria-hidden="true"><use href="#qil-i-compare"/></svg><span>${escapeHtml(selected ? t('selected') : t('addCompare'))}</span></button></div><button class="qil-ask-product" type="button" data-qimia-ai-open data-qil-ai-intent="product" data-qimia-product-id="${escapeHtml(product.id)}" data-qimia-product-name="${escapeHtml(product.name)}"><svg><use href="#qil-i-spark"/></svg><span>${escapeHtml(t('askAbout'))}</span><svg><use href="#qil-i-arrow"/></svg></button></div>
		</article>`;
	}

	function wcAjaxEndpoint(endpoint) {
		const raw = plain(config.wcAjaxUrl || '').replace('%%endpoint%%', endpoint).replace('%25%25endpoint%25%25', endpoint);
		if (!raw) return '';
		try {
			const url = new URL(raw, window.location.href);
			return url.origin === window.location.origin ? url.href : '';
		} catch (_) { return ''; }
	}

	function quickViewEndpoint(id) {
		try {
			const base = new URL(config.quickViewUrl || '', window.location.href);
			if (base.origin !== window.location.origin) return '';
			base.pathname = `${base.pathname.replace(/\/$/, '')}/${Math.max(0, Number(id) || 0)}`;
			base.searchParams.set('qil_schema', String(config.schemaVersion || ''));
			base.searchParams.set('qil_qv', '3');
			base.searchParams.set('qil_locale', state.lang === 'ar' ? 'ar' : 'en');
			return base.href;
		} catch (_) { return ''; }
	}

	function quickViewCacheToken(id) {
		return `${state.lang}|${String(id)}`;
	}

	function loadQuickViewData(id, signal = null) {
		const key = quickViewCacheToken(id);
		if (quickViewCache.has(key)) return Promise.resolve(quickViewCache.get(key));
		if (quickViewPrefetches.has(key)) return quickViewPrefetches.get(key);
		const endpoint = quickViewEndpoint(id);
		if (!endpoint) return Promise.reject(new Error('quick_view_endpoint'));
		const request = fetch(endpoint, {
			credentials:'same-origin', cache:'no-store', signal:signal || undefined, headers:languageHeaders({Accept:'application/json'})
		}).then(response => {
			if (!response.ok) throw new Error('quick_view_request');
			return response.json();
		}).then(data => {
			if (quickViewCache.size >= 12) quickViewCache.delete(quickViewCache.keys().next().value);
			quickViewCache.set(key, data);
			return data;
		});
		quickViewPrefetches.set(key, request);
		request.finally(() => {
			if (quickViewPrefetches.get(key) === request) quickViewPrefetches.delete(key);
		}).catch(() => {});
		return request;
	}

	function prefetchQuickView(id) {
		if (!id || !wcAjaxEndpoint('get_variation')) return;
		void loadQuickViewData(id).catch(() => {});
	}
	function prefetchQuickViewFromEvent(event) {
		const element = event.target instanceof Element ? event.target.closest('[data-qil-quick-view-id]') : null;
		if (element && root.contains(element)) prefetchQuickView(element.dataset.qilQuickViewId);
	}

	function moneyMarkup(value, product = null) {
		const amount = Number(value);
		if (!Number.isFinite(amount) || amount < 0) return '—';
		const decimals = Math.max(0, Math.min(4, Number(config.priceDecimals ?? 2) || 0));
		const number = new Intl.NumberFormat(state.lang === 'ar' ? 'ar-OM' : 'en-OM', {
			minimumFractionDigits: decimals,
			maximumFractionDigits: decimals
		}).format(amount);
		const symbol = plain(product?.price?.symbol || config.currencySymbol || product?.price?.currency || config.currency || '');
		return `<span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol" translate="no">${escapeHtml(symbol)}</span>&nbsp;${escapeHtml(number)}</bdi></span>`;
	}
	function quickViewMoney(value) { return moneyMarkup(value); }

	function quickViewPricingMarkup(regularValue, currentValue, showFrom = false) {
		const regular = Number(regularValue), current = Number(currentValue);
		const prefix = showFrom ? `<small class="qil-price-from" dir="auto">${escapeHtml(t('fromPrice'))}</small>` : '';
		if (!Number.isFinite(current) || current < 0) return `${prefix}<span class="qil-qv-sale is-current"><small>${escapeHtml(t('price'))}</small><strong>—</strong></span>`;
		const isSale = Number.isFinite(regular) && regular > current + 0.00001;
		const currency = plain(config.currency || '').toUpperCase();
		const currentSpoken = `${current} ${currency}`.trim();
		if (isSale) {
			const regularSpoken = `${regular} ${currency}`.trim();
			return `${prefix}<span class="qil-qv-regular"><small>${escapeHtml(t('regularPrice'))}</small><del aria-label="${escapeHtml(`${t('regularPrice')}: ${regularSpoken}`)}"><span aria-hidden="true">${quickViewMoney(regular)}</span></del></span><span class="qil-qv-sale"><small>${escapeHtml(t('salePrice'))}</small><strong aria-label="${escapeHtml(`${t('salePrice')}: ${currentSpoken}`)}"><span aria-hidden="true">${quickViewMoney(current)}</span></strong></span>`;
		}
		return `${prefix}<span class="qil-qv-sale is-current"><small>${escapeHtml(t('price'))}</small><strong aria-label="${escapeHtml(`${t('price')}: ${currentSpoken}`)}"><span aria-hidden="true">${quickViewMoney(current)}</span></strong></span>`;
	}

	function quickViewParentPricing(product) {
		const price = product?.price || {};
		const current = price.sale || price.current || price;
		const regular = price.regular || current;
		const currentMin = Number(current?.min ?? current?.value);
		const regularMin = Number(regular?.min ?? regular?.value);
		return quickViewPricingMarkup(regularMin, currentMin, product?.type === 'variable');
	}

	function safeExpiryDate(value) {
		const raw = plain(value);
		let year = 0, month = 0, day = 0, match = raw.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
		if (match) { day = Number(match[1]); month = Number(match[2]); year = Number(match[3]); }
		else {
			match = raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);
			if (match) { year = Number(match[1]); month = Number(match[2]); day = Number(match[3]); }
		}
		if (!year || year < 2020 || year > 2100 || month < 1 || month > 12 || day < 1 || day > 31) return null;
		const date = new Date(Date.UTC(year, month - 1, day));
		return date.getUTCFullYear() === year && date.getUTCMonth() === month - 1 && date.getUTCDate() === day ? date : null;
	}

	function formatExpiryDate(value) {
		const date = safeExpiryDate(value);
		if (!date) return '';
		try {
			return new Intl.DateTimeFormat(state.lang === 'ar' ? 'ar-OM' : 'en-GB', {day:'numeric', month:'short', year:'numeric', timeZone:'UTC'}).format(date);
		} catch (_) { return date.toISOString().slice(0, 10); }
	}

	function ensureQuickViewModal() {
		let modal = $('[data-qil-quick-view-modal]');
		if (modal) return modal;
		modal = document.createElement('div');
		modal.className = 'qil-modal qil-quick-view';
		modal.dataset.qilQuickViewModal = '1';
		modal.hidden = true;
		modal.setAttribute('aria-hidden', 'true');
		modal.innerHTML = `<div class='qil-modal-backdrop' data-qil-qv-backdrop></div><section class='qil-modal-panel qil-quick-view-panel' id='qil-quick-view-modal' role='dialog' aria-modal='true' aria-label='${escapeHtml(t('quickViewTitle'))}'><button class='qil-modal-close' type='button' data-qil-qv-close aria-label='${escapeHtml(t('close'))}'><svg aria-hidden='true'><use href='#qil-i-close'/></svg></button><div data-qil-qv-content></div></section>`;
		root.append(modal);
		return modal;
	}

	function quickViewError(modal, product, message = t('quickViewError')) {
		const target = $('[data-qil-qv-content]', modal);
		if (!target) return;
		target.innerHTML = `<div class='qil-qv-error' role='alert'><p>${escapeHtml(message)}</p><a class='qil-button qil-button-primary' href='${escapeHtml(safeUrl(product?.url))}'>${escapeHtml(t('viewFullProduct'))}</a></div>`;
	}

	function quickViewSelection(modal) {
		const selected = {};
		$$('[data-qil-variation-attribute]', modal).forEach(select => { selected[select.dataset.attributeKey] = select.value; });
		return selected;
	}

	function quickViewCombinationMatches(combination, selection, ignoredKey = '') {
		return Object.entries(selection).every(([key, selected]) => {
			if (key === ignoredKey || !plain(selected)) return true;
			const candidate = plain(combination?.[key]);
			return !candidate || candidate === plain(selected);
		});
	}

	function refreshQuickViewOptions(modal, changedKey = '') {
		if (!activeQuickView || activeQuickView.modal !== modal) return;
		const combinations = Array.isArray(activeQuickView.data.combinations) ? activeQuickView.data.combinations : [];
		const attributes = Array.isArray(activeQuickView.data.attributes) ? activeQuickView.data.attributes : [];
		if (!combinations.length || !attributes.length) return;
		const selects = $$('[data-qil-variation-attribute]', modal).sort((a, b) => Number(a.dataset.attributeKey === changedKey) - Number(b.dataset.attributeKey === changedKey));
		for (let pass = 0; pass < 2; pass += 1) {
			selects.forEach(select => {
				const key = select.dataset.attributeKey;
				const selection = quickViewSelection(modal);
				const attribute = attributes.find(item => item.key === key);
				const allValues = new Set((attribute?.options || []).map(option => plain(option.value)).filter(Boolean));
				const allowed = new Set();
				combinations.forEach(combination => {
					if (!quickViewCombinationMatches(combination, selection, key)) return;
					const value = plain(combination?.[key]);
					if (value) allowed.add(value);
					else allValues.forEach(item => allowed.add(item));
				});
				[...select.options].forEach(option => {
					if (!option.value) return;
					const available = allowed.has(option.value);
					option.hidden = !available;
					option.disabled = !available;
				});
				if (select.value && !allowed.has(select.value)) select.value = '';
			});
		}
	}

	function truthyVariationField(value) {
		return value === true || value === 1 || ['1', 'yes', 'true'].includes(plain(value).toLowerCase());
	}

	function setQuickViewFact(modal, key, value) {
		const row = $(`[data-qil-qv-${key}-row]`, modal);
		const target = $(`[data-qil-qv-${key}-value]`, modal);
		if (!row || !target) return false;
		const text = plain(value);
		row.hidden = !text;
		target.textContent = text;
		return Boolean(text);
	}

	function resetQuickViewFacts(modal) {
		const pricing = $('[data-qil-qv-pricing]', modal);
		if (pricing && activeQuickView?.product) pricing.innerHTML = quickViewParentPricing(activeQuickView.product);
		['promo', 'discount', 'expiry', 'days', 'stock', 'limit'].forEach(key => setQuickViewFact(modal, key, ''));
		const facts = $('[data-qil-qv-stock-facts]', modal);
		if (facts) facts.hidden = true;
	}

	function updateQuickViewImage(modal, variation) {
		const image = variation?.image;
		const target = $('.qil-qv-media img', modal);
		if (!target || !image || typeof image !== 'object') return;
		const src = safeUrl(image.src || image.full_src || '');
		if (!src) return;
		target.src = src;
		const srcset = plain(image.srcset);
		if (srcset) target.srcset = srcset;
		else target.removeAttribute('srcset');
		const sizes = plain(image.sizes);
		if (sizes) target.sizes = sizes;
		target.width = Math.max(1, Number(image.src_w || image.full_src_w || target.width || 600));
		target.height = Math.max(1, Number(image.src_h || image.full_src_h || target.height || 600));
		target.alt = plain(image.alt) || plain(activeQuickView?.product?.name);
	}

	function renderQuickViewVariationFacts(modal, variation) {
		const current = Number(variation?.display_price);
		const regular = Number(variation?.display_regular_price);
		const onSale = Number.isFinite(current) && Number.isFinite(regular) && regular > current + 0.00001;
		const pricing = $('[data-qil-qv-pricing]', modal);
		if (pricing) pricing.innerHTML = quickViewPricingMarkup(regular, current);

		// QIL's structured contract is appended server-side after the Flash Sale
		// plugin. Older allowlisted fields remain a bounded compatibility fallback;
		// price_html and arbitrary promotional markup are never inserted here.
		const contract = variation?.qil && typeof variation.qil === 'object' ? variation.qil : {};
		const promotion = contract?.promotion && typeof contract.promotion === 'object' ? contract.promotion : {};
		const inventory = contract?.inventory && typeof contract.inventory === 'object' ? contract.inventory : {};
		const eligible = promotion.type === 'flash' || truthyVariationField(variation?.qimia_flash_sale_eligible);
		const pluginDiscount = Math.max(0, Math.min(100, Number.parseInt(promotion.discountPct ?? variation?.qimia_flash_sale_total_percent, 10) || 0));
		const computedDiscount = onSale && regular > 0 ? Math.max(0, Math.min(100, Math.round(((regular - current) / regular) * 100))) : 0;
		const discount = eligible && pluginDiscount > 0 ? pluginDiscount : computedDiscount;
		const bestDeal = promotion.bestDeal === true || truthyVariationField(variation?.qimia_flash_sale_best_deal);
		const expiry = formatExpiryDate(promotion.expiry) || formatExpiryDate(variation?.qimia_flash_sale_expiry) || formatExpiryDate(variation?.expiry_date);
		const daysLeftValue = Number.parseInt(promotion.daysLeft ?? variation?.qimia_flash_sale_days_left, 10);
		const daysLeft = eligible && Number.isFinite(daysLeftValue) && daysLeftValue >= 0 && daysLeftValue <= 3650
			? `${new Intl.NumberFormat(state.lang === 'ar' ? 'ar-OM' : 'en-OM').format(daysLeftValue)} ${t('daysRemaining')}`
			: '';
		const stockValue = Number(inventory.stockQuantity ?? variation?.stock_qty);
		const limitValue = Number(inventory.maxQuantity ?? variation?.max_qty);
		const stockQuantity = Number.isFinite(stockValue) && stockValue > 0 ? Math.floor(stockValue) : 0;
		const orderLimit = Number.isFinite(limitValue) && limitValue > 0 ? Math.floor(limitValue) : 0;
		const stockText = stockQuantity > 0
			? `${new Intl.NumberFormat(state.lang === 'ar' ? 'ar-OM' : 'en-OM').format(stockQuantity)} ${t('unitsAvailable')}`
			: t('inStock');
		const limitText = orderLimit > 0
			? `${new Intl.NumberFormat(state.lang === 'ar' ? 'ar-OM' : 'en-OM').format(orderLimit)} ${t('maximumUnits')}`
			: '';

		const shown = [
			setQuickViewFact(modal, 'promo', eligible && pluginDiscount > 0 ? `${t('flashSale')}${bestDeal ? ` · ${t('bestDeal')}` : ''}` : ''),
			setQuickViewFact(modal, 'discount', discount > 0 ? `−${discount}${state.lang === 'ar' ? '٪' : '%'}` : ''),
			setQuickViewFact(modal, 'expiry', expiry),
			setQuickViewFact(modal, 'days', daysLeft),
			setQuickViewFact(modal, 'stock', stockText),
			setQuickViewFact(modal, 'limit', limitText)
		].some(Boolean);
		const facts = $('[data-qil-qv-stock-facts]', modal);
		if (facts) facts.hidden = !shown;
	}

	function resetQuickViewVariation(modal, message = t('selectAllOptions')) {
		window.clearTimeout(variationTimer);
		variationController?.abort();
		if (activeQuickView?.modal === modal) activeQuickView.requestId += 1;
		const add = $('[data-qil-qv-add]', modal);
		if (add) {
			add.classList.remove('add_to_cart_button', 'ajax_add_to_cart', 'product_type_variation', 'loading', 'added');
			add.classList.add('is-disabled');
			add.setAttribute('aria-disabled', 'true');
			add.setAttribute('tabindex', '-1');
			add.removeAttribute('data-product_id');
			add.removeAttribute('data-product_sku');
			if (window.jQuery) window.jQuery(add).removeData('product_id').removeData('product_sku');
		}
		const status = $('[data-qil-qv-message]', modal);
		if (status) status.textContent = message;
		resetQuickViewFacts(modal);
	}

	async function resolveQuickViewVariation(modal) {
		if (!activeQuickView || activeQuickView.modal !== modal) return;
		const selection = quickViewSelection(modal);
		const keys = activeQuickView.data.attributes.map(attribute => attribute.key);
		if (!keys.length || keys.some(key => !plain(selection[key]))) {
			resetQuickViewVariation(modal);
			return;
		}
		resetQuickViewVariation(modal, t('checkingVariation'));
		const endpoint = wcAjaxEndpoint('get_variation');
		if (!endpoint) { quickViewError(modal, activeQuickView.product); return; }
		const requestId = ++activeQuickView.requestId;
		const controller = new AbortController();
		variationController = controller;
			const body = new URLSearchParams();
			body.set('product_id', String(activeQuickView.data.id));
			body.set('qil_locale', state.lang === 'ar' ? 'ar' : 'en');
		keys.forEach(key => { if (/^attribute_[a-z0-9_-]+$/.test(key)) body.set(key, selection[key]); });
		try {
			const response = await fetch(endpoint, {method:'POST', credentials:'same-origin', signal:controller.signal, headers:languageHeaders({'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8','X-Requested-With':'XMLHttpRequest'}), body:body.toString()});
			if (!response.ok) throw new Error('variation_request');
			const variation = await response.json();
			if (!activeQuickView || activeQuickView.modal !== modal || activeQuickView.requestId !== requestId) return;
			const available = Number(variation?.variation_id || 0) > 0
				&& truthyVariationField(variation?.variation_is_active)
				&& truthyVariationField(variation?.variation_is_visible)
				&& truthyVariationField(variation?.is_purchasable)
				&& truthyVariationField(variation?.is_in_stock);
			if (!available) { resetQuickViewVariation(modal, t('variationUnavailable')); return; }

			renderQuickViewVariationFacts(modal, variation);
			updateQuickViewImage(modal, variation);
			const status = $('[data-qil-qv-message]', modal);
			if (status) status.textContent = variation?.availability_html ? (variation.is_in_stock ? t('inStock') : t('variationUnavailable')) : t('inStock');
			const add = $('[data-qil-qv-add]', modal);
			if (!add) return;
			const variationId = Math.max(0, Number(variation.variation_id) || 0);
			let addUrl = safeUrl(activeQuickView.product?.url);
			try {
				const cartUrl = new URL(config.cartUrl || activeQuickView.product?.url, window.location.href);
				cartUrl.searchParams.set('add-to-cart', String(variationId));
				cartUrl.searchParams.set('quantity', '1');
				addUrl = cartUrl.href;
			} catch (_) { /* The product URL remains a safe progressive fallback. */ }
			add.href = addUrl;
			add.classList.remove('is-disabled');
			add.classList.add('button', 'product_type_variation', 'add_to_cart_button', 'ajax_add_to_cart');
			add.setAttribute('aria-disabled', 'false');
			add.removeAttribute('tabindex');
			add.dataset.product_id = String(variationId);
			add.dataset.product_sku = plain(variation.sku || '');
			add.dataset.quantity = '1';
			if (window.jQuery) window.jQuery(add).data({product_id:variationId, product_sku:plain(variation.sku || ''), quantity:1});
		} catch (error) {
			if (error?.name !== 'AbortError' && activeQuickView?.modal === modal && activeQuickView.requestId === requestId) resetQuickViewVariation(modal, t('variationUnavailable'));
		}
	}

	function scheduleVariationCheck(modal) {
		window.clearTimeout(variationTimer);
		variationTimer = window.setTimeout(() => { void resolveQuickViewVariation(modal); }, 140);
	}

	function renderQuickView(modal, data, product) {
		if (data?.unavailable === true) { quickViewError(modal, product, t('quickViewUnavailable')); return; }
		if (data?.fallback === true || !Array.isArray(data?.attributes) || !data.attributes.length) { quickViewError(modal, product); return; }
		const defaults = data.defaults && typeof data.defaults === 'object' ? data.defaults : {};
		const fields = data.attributes.map((attribute, index) => {
			const options = Array.isArray(attribute.options) ? attribute.options : [];
			const defaultValue = plain(defaults[attribute.key] || (options.length === 1 ? options[0]?.value : ''));
			const inputId = `qil-qv-attribute-${index}`;
			return `<div class='qil-qv-attribute'><label for='${inputId}'>${escapeHtml(attribute.label)}</label><select id='${inputId}' data-qil-variation-attribute data-attribute-key='${escapeHtml(attribute.key)}'><option value=''>${escapeHtml(t('chooseOption'))}</option>${options.map(option => `<option value='${escapeHtml(option.value)}'${plain(option.value) === defaultValue ? ' selected' : ''}>${escapeHtml(option.label)}</option>`).join('')}</select></div>`;
		}).join('');
		const target = $('[data-qil-qv-content]', modal);
		if (!target) return;
		target.innerHTML = `<div class='qil-qv-layout'><div class='qil-qv-media'>${imageMarkup(product)}</div><div class='qil-qv-content'><small>${escapeHtml(brandName(product))}</small><h2 id='qil-quick-view-title'>${escapeHtml(product?.name || data.name || '')}</h2><div class='qil-qv-pricing' data-qil-qv-pricing dir='ltr'>${quickViewParentPricing(product)}</div><dl class='qil-qv-stock-facts' data-qil-qv-stock-facts hidden><div class='qil-qv-promo' data-qil-qv-promo-row hidden><dt>${escapeHtml(t('offer'))}</dt><dd data-qil-qv-promo-value></dd></div><div data-qil-qv-discount-row hidden><dt>${escapeHtml(t('discountFact'))}</dt><dd data-qil-qv-discount-value dir='ltr'></dd></div><div data-qil-qv-expiry-row hidden><dt>${escapeHtml(t('expiryFact'))}</dt><dd data-qil-qv-expiry-value></dd></div><div data-qil-qv-days-row hidden><dt>${escapeHtml(t('daysLeftFact'))}</dt><dd data-qil-qv-days-value></dd></div><div data-qil-qv-stock-row hidden><dt>${escapeHtml(t('stockFact'))}</dt><dd data-qil-qv-stock-value></dd></div><div data-qil-qv-limit-row hidden><dt>${escapeHtml(t('orderLimitFact'))}</dt><dd data-qil-qv-limit-value></dd></div></dl><div class='qil-qv-form'>${fields}<p class='qil-qv-status' data-qil-qv-message role='status' aria-live='polite'>${escapeHtml(t('selectAllOptions'))}</p><div class='qil-qv-actions'><a class='qil-buy qil-qv-add is-disabled' data-qil-qv-add data-qil-parent-id='${escapeHtml(data.id)}' href='${escapeHtml(safeUrl(product?.url))}' aria-disabled='true' tabindex='-1' rel='nofollow'><span>${escapeHtml(t('addSelectedToBag'))}</span><svg aria-hidden='true'><use href='#qil-i-cart-plus'/></svg></a><a class='qil-qv-details' href='${escapeHtml(safeUrl(product?.url))}'>${escapeHtml(t('viewFullProduct'))}</a></div></div></div></div>`;
		activeQuickView = {modal, data, product, requestId:0};
		refreshQuickViewOptions(modal);
		if ($$('[data-qil-variation-attribute]', modal).every(select => plain(select.value))) scheduleVariationCheck(modal);
	}

	async function openQuickView(id) {
		const product = actionProduct(String(id));
		if (!product) return;
		const endpoint = quickViewEndpoint(id);
		if (!endpoint || !wcAjaxEndpoint('get_variation')) { window.location.assign(safeUrl(product.url)); return; }
		const modal = ensureQuickViewModal();
		modal.dataset.qilQuickViewId = String(id);
        const presentation = window.QILPersonalization?.originFor(id);
        if (presentation) modal.dataset.qilPresentation = presentation; else delete modal.dataset.qilPresentation;
		const target = $('[data-qil-qv-content]', modal);
		if (target) target.innerHTML = `<div class='qil-qv-loading' role='status'>${escapeHtml(t('quickViewLoading'))}</div>`;
		openModal(modal);
		quickViewController?.abort();
		variationController?.abort();
		activeQuickView = null;
		const controller = new AbortController();
		quickViewController = controller;
		try {
			const data = await loadQuickViewData(id, controller.signal);
			if (modal.hidden || modal.dataset.qilQuickViewId !== String(id)) return;
			renderQuickView(modal, data, product);
		} catch (error) {
			if (error?.name !== 'AbortError' && !modal.hidden) quickViewError(modal, product);
		}
	}

	function closeQuickView(modal = $('[data-qil-quick-view-modal]')) {
		window.clearTimeout(variationTimer);
		quickViewController?.abort();
		variationController?.abort();
		activeQuickView = null;
		closeModal(modal);
	}

	function renderProducts() {
		const grid = $('[data-qil-results]');
		if (!grid) return;
		if (goalPending && goalPendingPage === 1) { updateGoalFeedback([]); return; }
		const ranked = recommendationSet().products;
		grid.innerHTML = ranked.slice(0, state.visible).map(productCard).join('');
		grid.hidden = ranked.length === 0;
		updateGoalFeedback(ranked);
		observeReveals();
	}

	// One commit per accepted response. Pagination keeps already-validated cards in place.
	function updateGoalFeedback(ranked = null) {
		const grid = $('[data-qil-results]'), empty = $('[data-qil-empty]');
		const waiting = goalPending && goalPendingPage === 1;
		// Do not publish an index-only recommendation context while a new result is pending.
		if (!Array.isArray(ranked)) ranked = waiting ? [] : recommendationSet().products;
		grid?.setAttribute('aria-busy', String(goalPending));
		if (grid) grid.hidden = waiting || ranked.length === 0;
		const loader = $('[data-qil-goal-loading]');
		if (loader) {
			loader.hidden = !waiting;
			const label = $('[data-qil-goal-loading-label]', loader), detail = $('[data-qil-goal-loading-detail]', loader);
			if (label) label.textContent = state.lang === 'ar' ? 'نجهّز اختياراتك' : 'Preparing your picks';
			if (detail) detail.textContent = state.lang === 'ar' ? 'نتحقق من المنتجات والأسعار والتوفر' : 'Checking products, prices and availability';
		}
		if (empty) {
			empty.hidden = waiting || ranked.length > 0;
			if (!waiting && !ranked.length) {
				const cause = goalError ? (state.lang === 'ar' ? 'تعذّر تحميل المنتجات الآن' : 'Products could not load right now') : state.prefs.size ? (state.lang === 'ar' ? 'لا توجد منتجات موثّقة تطابق الفلاتر المحددة.' : 'No explicitly verified products match these filters.') : (state.search ? t('noSearchResults') : t('noMatches'));
				const hint = goalError ? (state.lang === 'ar' ? 'اضغط إعادة المحاولة لتحميل النتائج.' : 'Use Retry to load the results again.') : t('noMatchesSub');
				empty.innerHTML = `<strong>${escapeHtml(cause)}</strong><p>${escapeHtml(hint)}</p><div class="qil-empty-actions">${state.prefs.size || state.search ? `<button type="button" class="qil-text-button" data-qil-clear-filters>${state.lang === 'ar' ? 'مسح الفلاتر والبحث' : 'Clear filters and search'}</button>` : ''}<button class="qil-text-button" type="button" data-qimia-ai-open data-qil-ai-intent="routine">${escapeHtml(t('askRefine'))}</button></div>`;
			}
		}
		const status = $('[data-qil-goal-status]');
		if (status) { status.textContent = goalStatusText(); status.dataset.state = goalError ? 'error' : (goalPending ? 'loading' : 'ready'); }
		const more = $('[data-qil-show-more]');
		if (more) { more.hidden = waiting || (!goalError && !goalHasMore && ranked.length <= state.visible); more.disabled = goalPending; more.setAttribute('aria-busy', String(goalPending)); const label = more.querySelector('span'); if (label) label.textContent = goalError ? (state.lang === 'ar' ? 'إعادة المحاولة' : 'Retry') : t('showMore'); }
	}

	function collectionProducts(key) {
		const ids = Array.isArray(config?.collections?.[key]) ? config.collections[key] : [];
		const matches = ids.map(id => productIndex.get(String(id))).filter(Boolean);
		return (key === 'best-sellers' || key === 'weekly-best-sellers')
			? matches.filter(product => Number(product?.salesCount || 0) > 0)
			: matches;
	}

	function renderCollections() {
		$$('[data-qil-collection]').forEach(section => {
			const key = plain(section.dataset.qilCollection);
			const grid = $('[data-qil-collection-grid]', section);
			if (!grid) return;
			const matches = collectionProducts(key).slice(0, 8);
			section.hidden = matches.length === 0;
			grid.innerHTML = matches.map((product, index) => productCard(product, index, key)).join('');
			grid.scrollLeft = 0;
		});
		setupRails();
		observeReveals();
	}

	function renderCompareShowcase() {
		const target = $('[data-qil-compare-showcase]'); if (!target) return;
		const permitted = recommendationSet().products;
		const allowed = new Set(permitted.map(product => Number(product.id)));
		const chosen = state.compare.filter(product => allowed.has(Number(product.id)));
		const sample = chosen.length === 2 ? chosen : permitted.slice(0,2);
		if (sample.length < 2) { target.innerHTML = `<div class="qil-compare-empty">${escapeHtml(t('comparisonEmpty'))}</div>`; return; }
		target.innerHTML = `<div class="qil-compare-products"><span class="qil-compare-label-spacer">${escapeHtml(t('product'))}</span>${sample.map(product => `<div>${imageMarkup(product)}<span>${escapeHtml(brandName(product))}</span><strong>${escapeHtml(product.name)}</strong></div>`).join('')}</div><div class="qil-compare-row"><span>${escapeHtml(t('price'))}</span>${sample.map(product => `<div>${comparePriceMarkup(product)}</div>`).join('')}</div><div class="qil-compare-row"><span>${escapeHtml(t('purpose'))}</span><strong>${escapeHtml(productPurpose(sample[0]))}</strong><strong>${escapeHtml(productPurpose(sample[1]))}</strong></div><div class="qil-compare-row"><span>${escapeHtml(t('keyActive'))}</span><strong>${escapeHtml(activeValue(sample[0]) || t('notListed'))}</strong><strong>${escapeHtml(activeValue(sample[1]) || t('notListed'))}</strong></div>`;
	}

	function scrollToProductResults() {
		const target = $('.qil-results-head') || $('[data-qil-results]');
		if (!target) return;
		const header = $('[data-qil-header]');
		const headerHeight = header && getComputedStyle(header).position === 'sticky' ? header.getBoundingClientRect().height : 0;
		const mobileGap = window.matchMedia('(max-width: 760px)').matches ? 12 : 18;
		const top = Math.max(0, target.getBoundingClientRect().top + window.scrollY - headerHeight - mobileGap);
		window.scrollTo({top, behavior:window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'});
	}

	function setGoal(goal, shouldScroll = false) {
		if (!goalLabels()[goal]) return;
		if (state.goal === goal && goalTouched && goalResultKey === goalContextKey()) {
			if (!goalPending) void loadGoalPage(1);
			if (shouldScroll) window.requestAnimationFrame(scrollToProductResults);
			return;
		}
		state.goal = goal; state.visible = 8; goalTouched = true;
		invalidateRecommendations(); syncGoalControls(); updateGoalLabels(); queueGoalPage(); refreshRecommendationSurfaces();
		if (shouldScroll) trackSessionIntent('goal', goal);
		if (shouldScroll) window.requestAnimationFrame(scrollToProductResults);
	}

	function syncNativeCompareContext(chat = window.AmirAIChat) {
		if (!chat) return false;
		try {
			const records = state.compare.slice(0, 2).map(aiProductRecord).filter(record => record.id > 0);
			chat.compareIds = records.map(record => record.id);
			if (!(chat.compareProducts instanceof Map)) chat.compareProducts = new Map();
			else chat.compareProducts.clear();
			records.forEach(record => chat.compareProducts.set(record.id, record));
			chat.refreshCompareButtons?.();
			chat.renderCompareDock?.();
			return records.length === state.compare.length;
		} catch (_) { return false; }
	}

	function toggleCompare(id) {
		const existing = state.compare.findIndex(item => String(item.id) === String(id));
		if (existing >= 0) { state.compare.splice(existing, 1); showToast(t('removedCompare')); }
		else {
            if (state.compare.length >= 2) return showToast(t('maxCompare'));
            const product = actionProduct(String(id));
            if (!product) return;
            state.compare.push(product);
            if (state.compare.length === 1) showToast(t('addedCompare'));
            else { clearTimeout(toastTimer); hideToast(); }
        }
		syncCompareButtons(); renderCompare(); renderCompareShowcase(); syncNativeCompareContext(); syncAILauncherOffset();
	}

	function syncCompareButtons() {
		const selectedIds = new Set(state.compare.map(product => String(product.id)));
		$$('[data-compare-id]').forEach(button => {
			const selected = selectedIds.has(String(button.dataset.compareId));
			button.classList.toggle('is-active', selected);
			button.setAttribute('aria-label', selected ? t('removeCompare') : t('addCompare'));
			button.setAttribute('aria-pressed', selected ? 'true' : 'false');
			button.title = selected ? t('removeCompare') : t('addCompare');
			const label = button.querySelector('span');
			if (label) label.textContent = selected ? t('selected') : t('addCompare');
		});
	}

	function compareFact(product, key) {
		if (key === 'price') return priceLabel(product);
		if (key === 'active') return activeValue(product);
		if (key === 'servings') return factValue(product, ['servingsPerContainer','servings']) || plain(product.servings);
		if (key === 'servingSize') return factValue(product, ['servingSize']);
		if (key === 'caffeine') return factValue(product, ['caffeine','caffeineMgTotal']);
		if (key === 'dietary') { const labels = []; if (product?.dietary?.vegan === true) labels.push(state.lang === 'ar' ? 'نباتي' : 'Vegan'); if (product?.dietary?.halal === true) labels.push(state.lang === 'ar' ? 'حلال' : 'Halal'); if (product?.dietary?.stimulantFree === true) labels.push(t('stimFree')); return labels.join(' · '); }
		return '';
	}

	function compareMobileCard(product, index) {
		const titleId = `qil-compare-mobile-title-${Math.max(0, Number(product?.id || index))}`;
		const image = primaryImages(product)[0];
		const facts = [
			[t('purpose'), productPurpose(product)],
			[t('keyActive'), compareFact(product, 'active')],
			[t('servingSize'), compareFact(product, 'servingSize')],
			[t('servings'), compareFact(product, 'servings')],
			[t('caffeineFact'), compareFact(product, 'caffeine')],
			[t('dietaryFit'), compareFact(product, 'dietary')]
		];
		return `<article class="qil-compare-mobile-card" data-product-id="${escapeHtml(product.id)}" aria-labelledby="${escapeHtml(titleId)}"><header>${image ? `<img src="${escapeHtml(safeUrl(image.src || image.url))}"${image.srcset ? ` srcset="${escapeHtml(image.srcset)}"` : ''} sizes="72px" width="${Math.max(1, Number(image.width || 600))}" height="${Math.max(1, Number(image.height || 600))}" alt="" loading="lazy" decoding="async">` : ''}<div><small>${escapeHtml(brandName(product))}</small><h3 id="${escapeHtml(titleId)}">${escapeHtml(product.name)}</h3></div></header><dl><div><dt>${escapeHtml(t('price'))}</dt><dd>${comparePriceMarkup(product)}</dd></div>${facts.map(([label, value]) => `<div><dt>${escapeHtml(label)}</dt><dd>${escapeHtml(value || t('notListed'))}</dd></div>`).join('')}</dl><button class="qil-text-button" type="button" data-remove-compare="${escapeHtml(product.id)}" aria-label="${escapeHtml(`${t('remove')}: ${product.name}`)}">${escapeHtml(t('remove'))}</button></article>`;
	}

	function syncCompareLayoutMode(scope = $('[data-qil-compare-table]')) {
		if (!scope) return;
		const mobile = window.matchMedia('(max-width: 760px)').matches;
		const desktopView = $('[data-qil-compare-desktop]', scope);
		const mobileView = $('[data-qil-compare-mobile]', scope);
		if (desktopView) desktopView.hidden = mobile;
		if (mobileView) mobileView.hidden = !mobile;
	}

	function renderCompare() {
		const compareDockActive = state.compare.length >= 2;
		const dock = $('[data-qil-compare-dock]'); if (dock) dock.hidden = !compareDockActive;
		root.classList.toggle('has-qil-compare-dock', compareDockActive);
		syncProductBuyBarVisibility();
		syncAILauncherOffset();
		const count = $('[data-qil-compare-count]'); if (count) count.textContent = `${state.compare.length}/2`;
		const thumbs = $('[data-qil-compare-thumbs]'); if (thumbs) thumbs.innerHTML = state.compare.map(product => { const image = primaryImages(product)[0]; return `<span class="qil-compare-thumb">${image ? `<img src="${escapeHtml(safeUrl(image.src || image.url))}" alt="">` : 'Q'}</span>`; }).join('');
		const target = $('[data-qil-compare-table]'); if (!target) return;
		if (state.compare.length < 2) { target.innerHTML = `<div class="qil-compare-empty">${escapeHtml(t('comparisonEmpty'))}</div>`; return; }
		const heads = state.compare.map(product => { const image = primaryImages(product)[0]; return `<th scope='col'>${image ? `<img src='${escapeHtml(safeUrl(image.src || image.url))}' alt=''>` : ''}<span>${escapeHtml(product.name)}</span></th>`; }).join('');
		const row = (label, key, dir = '') => `<tr><th scope='row'>${escapeHtml(label)}</th>${state.compare.map(product => `<td${dir ? ` dir='${dir}'` : ''}>${escapeHtml(compareFact(product,key) || t('notListed'))}</td>`).join('')}</tr>`;
		const priceRow = `<tr><th scope='row'>${escapeHtml(t('price'))}</th>${state.compare.map(product => `<td>${comparePriceMarkup(product)}</td>`).join('')}</tr>`;
		const mobileCards = state.compare.map(compareMobileCard).join('');
		target.innerHTML = `<table class='qil-compare-table' data-qil-compare-desktop><caption class='screen-reader-text'>${escapeHtml(t('sideBySide'))}</caption><thead><tr><th scope='col'>${escapeHtml(t('product'))}</th>${heads}</tr></thead><tbody>${priceRow}<tr><th scope='row'>${escapeHtml(t('purpose'))}</th>${state.compare.map(product => `<td>${escapeHtml(productPurpose(product))}</td>`).join('')}</tr>${row(t('keyActive'),'active')}${row(t('servingSize'),'servingSize')}${row(t('servings'),'servings')}${row(t('caffeineFact'),'caffeine')}${row(t('dietaryFit'),'dietary')}<tr><th scope='row'>${escapeHtml(t('remove'))}</th>${state.compare.map(product => `<td><button class='qil-text-button' type='button' data-remove-compare='${escapeHtml(product.id)}' aria-label='${escapeHtml(`${t('remove')}: ${product.name}`)}'>${escapeHtml(t('remove'))}</button></td>`).join('')}</tr></tbody></table><div class='qil-compare-mobile' data-qil-compare-mobile>${mobileCards}</div><div class='qil-compare-ai-action'><button class='qil-button qil-button-primary' type='button' data-qimia-ai-open data-qil-ai-intent='compare-selected'><svg><use href='#qil-i-spark'/></svg><span>${escapeHtml(t('askAICompare'))}</span><span class='qil-button-meta'>LIVE</span></button><div><strong>${escapeHtml(t('aiVerdict'))}</strong><small>${escapeHtml(t('compareReady'))}</small></div></div>`;
		syncCompareLayoutMode(target);
		$$('[data-remove-compare]', target).forEach(button => button.addEventListener('click', () => toggleCompare(button.dataset.removeCompare)));
	}

	function aiPrompt(intent, product = null) {
		const name = plain(product?.name || '');
		const id = Math.max(0, Number(product?.id || 0));
		if (state.lang === 'ar') {
			if (intent === 'product') return `أريد مناقشة هذا المنتج المحدد من متجر كيميا (رقم المنتج ${id}): ${name}. تحقّق أولاً من سجل ووكومرس المباشر وحقائق المكمّل الموثّقة قبل الإجابة.`;
			if (intent === 'protein') return 'ساعدني في اختيار بروتين مناسب لبناء عضلات صافية من كتالوج كيميا المباشر. ابدأ بسؤال واحد مختصر عن احتياجي اليومي من البروتين ونظامي الغذائي وميزانيتي، واستخدم المخزون والأسعار الحالية فقط.';
			if (intent === 'delivery') return 'تحقّق من التوصيل إلى بلدي بالعملة الحالية، واذكر حد التوصيل المجاني المباشر. اسأل عن المدينة فقط إذا احتجت إليها.';
			if (intent === 'routine') return 'ابدأ منشئ روتين المكمّلات الموجّه من كيميا. اسألني سؤالاً مختصراً واحداً في كل مرة: ابدأ بهدفي الرئيسي، ثم جدول التمرين، والقيود الغذائية، وتفضيل المنشّطات، والميزانية. استخدم منتجات كيميا المتوفرة مباشرة فقط ولا تقدّم تشخيصاً طبياً.';
		} else {
			if (intent === 'product') return `I want to discuss this exact Qimia store product (product ID ${id}): ${name}. First verify its live WooCommerce record and verified Supplement Facts before answering.`;
			if (intent === 'protein') return 'Help me choose a protein for lean muscle from the live Qimia catalogue. Start with one concise question about my daily protein target, diet and budget, and use only current stock and prices.';
			if (intent === 'delivery') return 'Check delivery for my current country and currency, and state the live free-delivery threshold. Ask for my city only if needed.';
			if (intent === 'routine') return 'Start Qimia\'s guided supplement routine builder. Ask me one concise question at a time: begin with my main goal, then training schedule, dietary restrictions, stimulant preference and budget. Use only live Qimia products and do not diagnose.';
		}
		return '';
	}

	function waitForQimiaAI(requireStore = false, timeout = 12000) {
		return new Promise((resolve, reject) => {
			const started = Date.now();
			const check = () => {
				const chat = window.AmirAIChat;
				// bootstrapData is assigned before renderInitialConversation finishes.
				// Waiting for bootstrapPromise to settle prevents that final render from
				// replacing a product draft or its currentProductIds immediately afterward.
				const storeReady = chat?.bootstrapData?.rest?.base && !chat?.bootstrapPromise;
				const ready = chat?.panel && chat?.input && (!requireStore || storeReady);
				if (ready) return resolve(chat);
				if (Date.now() - started >= timeout) return reject(new Error('qimia_ai_timeout'));
				window.setTimeout(check, 80);
			};
			check();
		});
	}

	function aiProductRecord(product) {
		const image = primaryImages(product)[0] || {};
		return {
			id: Math.max(0, Number(product?.id || 0)),
			name: plain(product?.name || ''),
			short_name: plain(product?.name || '').slice(0, 64),
			image: safeUrl(image.src || image.url || ''),
			price: product?.price || {},
			url: safeUrl(product?.url || '')
		};
	}

	async function handleQimiaAIIntent(trigger) {
		if (!trigger || trigger.dataset.qilAiBusy === '1') return;
		const intent = plain(trigger.dataset.qilAiIntent || 'open');
		if (intent === 'open') return;
		trigger.dataset.qilAiBusy = '1';
		showToast(t('aiConnecting'));
		try {
			const requireStore = intent === 'product' || intent === 'compare-product' || intent === 'compare-selected' || intent === 'compare-picker';
			const chat = await waitForQimiaAI(requireStore);
			chat.open?.();
			if (typeof chat.setLanguage === 'function') chat.setLanguage(state.lang, false);

			if (intent === 'product') {
				const id = Math.max(0, Number(trigger.dataset.qimiaProductId || trigger.dataset.qilProductId || config.productId || 0));
				const product = products.find(item => Number(item?.id || 0) === id);
				if (!product) throw new Error('missing_product');
				trackSessionIntent('product', id);
				let prompt = aiPrompt('product', product);
				const questions = state.lang === 'ar' ? {ingredients:'اشرح المكونات والكميات المدرجة، ووضّح المعلومات غير المتاحة.', usage:'اشرح تعليمات الاستخدام المكتوبة على الملصق فقط؛ لا تفترض جرعة.', suitability:'اسألني عما تحتاج معرفته لتقييم ملاءمة المنتج لهدفي؛ لا تقدّم تشخيصاً.', alternatives:'ابحث عن بدائل متوفرة من نفس فئة الاستخدام وقارن السعر لكل حصة عندما يكون معلوماً.'} : {ingredients:'Explain the listed ingredients and quantities, marking any missing information.', usage:'Explain only the label directions; do not invent a dose.', suitability:'Ask what you need to assess fit for my goal, without diagnosis.', alternatives:'Find in-stock alternatives with the same purpose and compare cost per serving only where known.'};
				if (questions[trigger.dataset.qilProductQuestion]) prompt += ' ' + questions[trigger.dataset.qilProductQuestion];
				const form = [...document.querySelectorAll('form.variations_form')].find(f => Number(f.dataset.product_id) === id);
				const variationId = Number(form?.querySelector('[name="variation_id"]')?.value || 0);
				const options = [...(form?.querySelectorAll('select[name^="attribute_"]') || [])].filter(f=>f.value).map(f=>f.options[f.selectedIndex]?.text || f.value).join(' · ');
				if (variationId) prompt += ` Selected variation ID: ${variationId}. ${options}. Verify this exact variant; do not infer a dose.`;
				window.QimiaShoppingContext?.select?.(id,variationId);
				chat.state.selectedVariation = variationId;
				chat.state.currentProductIds = [id];
				chat.state.selectedProduct = id;
				chat.input.value = prompt.slice(0, 1800);
				chat.state.draft = chat.input.value;
				chat.autoSizeInput?.();
				try { chat.input.focus({preventScroll:true}); } catch (_) { chat.input.focus?.(); }
				showToast(t('aiProductReady'));
				return;
			}

			if (intent === 'compare-product') {
				const id = Number(trigger.dataset.qimiaProductId || config.productId || 0);
				const product = actionProduct(String(id));
				if (!product || typeof chat.openComparePicker !== 'function' || !(chat.compareProducts instanceof Map)) throw new Error('compare_unavailable');
				chat.compareIds = [id];
				chat.compareProducts.set(id, aiProductRecord(product));
				chat.renderCompareBar?.();
				await chat.openComparePicker();
				return;
			}

			if (intent === 'compare-picker') {
				chat.openComparePicker?.();
				return;
			}

			if (intent === 'compare-selected') {
				if (state.compare.length !== 2) throw new Error('compare_requires_two');
				closeModal($('[data-qil-compare-modal]'));
				if (!syncNativeCompareContext(chat)) throw new Error('compare_context_unavailable');
				if (typeof chat.openCompareSheet !== 'function') throw new Error('compare_unavailable');
				await chat.openCompareSheet();
				return;
			}

			let prompt = aiPrompt(intent);
			if (!prompt) return;
			if ((intent === 'routine' || intent === 'protein') && $('[data-qil-results]')) {
				const context = recommendationSet().context;
				chat.qimiaRecommendationContext = context;
				if (typeof chat.setRecommendationContext === 'function') chat.setRecommendationContext(context);
				if (chat.state) chat.state.currentProductIds = [...context.productIds];
				prompt = (state.lang === 'ar' ? 'هدفي المحدد هو ' : 'My selected goal is ') + goalLabels()[context.goal] + '. ' + (state.lang === 'ar' ? 'تابع من هذه القائمة المسموح بها فقط، ولا تسأل عن الهدف مرة أخرى. تحقّق من المخزون وحقائق المنتج. إذا كانت القائمة فارغة فلا تخترع بديلاً. سياق التسوق: ' : 'Continue only from this permitted shortlist; do not ask my goal again. Recheck live stock and label facts. If the list is empty, explain the mismatch rather than inventing an alternative. Shopping context: ') + JSON.stringify({schemaVersion:context.schemaVersion,goal:context.goal,filters:context.filters,query:context.query,market:context.market,productIds:context.productIds});
			}
			const labels = {protein:t('promptProtein'), delivery:t('promptDelivery'), routine:t('buildRoutine')};
			await chat.send(prompt, labels[intent] || prompt, {languageOverride:state.lang, source:`qimia-home-${intent}`});
		} catch (_) {
			// Keep selected products usable if the assistant is temporarily offline.
			if (intent === 'compare-selected') { renderCompare(); openModal($('[data-qil-compare-modal]')); }
			showToast(t('aiUnavailable'));
		} finally {
			window.setTimeout(() => { delete trigger.dataset.qilAiBusy; }, 900);
		}
	}

	function setHeroAIStatus(message, stateName = '') {
		const status = $('[data-qil-hero-ai-status]');
		if (!status) return;
		status.textContent = plain(message);
		if (stateName) status.dataset.state = stateName;
		else delete status.dataset.state;
	}

	async function submitHeroAIQuery(form) {
		if (!form || form.dataset.qilAiBusy === '1') return;
		const input = $('[data-qil-hero-ai-input]', form);
		const submit = $('[data-qil-hero-ai-submit]', form);
		const prompt = plain(input?.value).slice(0, 600);
		if (!prompt) {
			setHeroAIStatus(t('heroPromptEmpty'), 'error');
			input?.focus();
			return;
		}
		form.dataset.qilAiBusy = '1';
		form.setAttribute('aria-busy', 'true');
		if (submit) submit.disabled = true;
		setHeroAIStatus(t('heroPromptConnecting'), 'loading');
		try {
			// The chat plugin may defer its own bootstrap until the first explicit
			// opener interaction. Reuse that public hook instead of loading a second
			// assistant or creating a parallel conversation.
			if (!window.AmirAIChat) $('[data-qimia-ai-open]')?.click();
			const chat = await waitForQimiaAI(true);
			if (typeof chat.send !== 'function') throw new Error('qimia_ai_send_unavailable');
			chat.open?.();
			if (typeof chat.setLanguage === 'function') chat.setLanguage(state.lang, false);
			await chat.send(prompt, prompt, {languageOverride:state.lang, source:'qimia-home-natural-language'});
			setHeroAIStatus(t('heroPromptSent'), 'success');
		} catch (_) {
			setHeroAIStatus(t('aiUnavailable'), 'error');
			showToast(t('aiUnavailable'));
		} finally {
			delete form.dataset.qilAiBusy;
			form.removeAttribute('aria-busy');
			if (submit) submit.disabled = false;
		}
	}

	function curatedPurposeProduct(key) {
		const sourceKey = key === 'fat_burner' ? 'fat-burner' : key;
		const curated = collectionProducts(sourceKey).find(product => productPurposeKey(product) === key && primaryImages(product).length);
		return curated || products.find(product => productPurposeKey(product) === key && primaryImages(product).length) || null;
	}

	// The routine stage points at categories. A representative packshot from the
	// live index illustrates each one, but the card links to the archive and is
	// titled with the category, never with one product.
	function purposeLabel(key) {
		const labels = state.lang === 'ar'
			? {protein:'بروتين', creatine:'كرياتين', mass_gainer:'زيادة الوزن', fat_burner:'دعم التنشيف', pre_workout:'قبل التمرين'}
			: {protein:'Protein', creatine:'Creatine', mass_gainer:'Mass gainer', fat_burner:'Cut support', pre_workout:'Pre-workout'};
		const extra = state.lang === 'ar' ? {amino_recovery:'أحماض أمينية',hydration:'ترطيب',joint_support:'دعم المفاصل',recovery_support:'تعافٍ',sleep_support:'دعم النوم',daily_wellness:'عافية يومية',omega_support:'أوميغا',beauty_support:'جمال',energy_support:'طاقة',nootropic:'تركيز'} : {amino_recovery:'Amino recovery',hydration:'Hydration',joint_support:'Joint support',recovery_support:'Recovery',sleep_support:'Sleep support',daily_wellness:'Daily wellness',omega_support:'Omega support',beauty_support:'Beauty',energy_support:'Energy',nootropic:'Focus'};
		return labels[key] || extra[key] || '';
	}
	function categoryArchiveUrl(key) {
		const urls = config.categoryUrls && typeof config.categoryUrls === 'object' ? config.categoryUrls : {};
		return safeUrl(urls[key] || config.shopUrl || '');
	}
	function editorialCategoryMarkup(key, index) {
		const product = curatedPurposeProduct(key);
		const image = product ? primaryImages(product)[0] : null;
		const label = purposeLabel(key);
		const url = categoryArchiveUrl(key);
		if (!url) return '';
		const media = image
			? `<img src="${escapeHtml(safeUrl(image.src || image.url))}"${image.srcset ? ` srcset="${escapeHtml(image.srcset)}"` : ''} sizes="(max-width:680px) 24vw, 130px" width="${Math.max(1,Number(image.width || 600))}" height="${Math.max(1,Number(image.height || 600))}" alt="" loading="lazy" decoding="async" style="transform:none;transition:none;animation:none">`
			: '<span class="qil-editorial-placeholder" aria-hidden="true"></span>';
		const shopLabel = state.lang === 'ar' ? `تسوّق ${label}` : `Shop ${label}`;
		return `<a class="qil-editorial-product qil-editorial-category qil-editorial-product-${index + 1}" href="${escapeHtml(url)}" aria-label="${escapeHtml(shopLabel)}">${media}<span>${escapeHtml(label)}</span></a>`;
	}

	function editorialProductMarkup(product, index, key) {
		if (!product) return '';
			const image = primaryImages(product)[0];
			if (!image) return '';
			const labels = state.lang === 'ar'
				? {protein:'بروتين',creatine:'كرياتين',mass_gainer:'زيادة الوزن',fat_burner:'دعم التنشيف',pre_workout:'قبل التمرين'}
				: {protein:'Protein',creatine:'Creatine',mass_gainer:'Mass gainer',fat_burner:'Cut support',pre_workout:'Pre-workout'};
			const label = purposeLabel(key);
			return `<a class="qil-editorial-product qil-editorial-product-${index + 1}" href="${escapeHtml(safeUrl(product.url))}" aria-label="${escapeHtml(product.name)}"><img src="${escapeHtml(safeUrl(image.src || image.url))}"${image.srcset ? ` srcset="${escapeHtml(image.srcset)}"` : ''} sizes="(max-width:680px) 22vw, 110px" width="${Math.max(1,Number(image.width || 600))}" height="${Math.max(1,Number(image.height || 600))}" alt="${escapeHtml(product.name)}" loading="lazy" decoding="async" style="transform:none;transition:none;animation:none"><span>${escapeHtml(label)}</span></a>`;
	}

	function weeklyHeroProductMarkup(product, index) {
		if (!product) return '';
		const image = primaryImages(product)[0];
		if (!image) return '';
		const compactName = plain(product?.name || brandName(product));
		return `<a class="qil-editorial-product qil-weekly-product qil-editorial-product-${index + 1}" href="${escapeHtml(safeUrl(product.url))}" aria-label="${escapeHtml(compactName)}" title="${escapeHtml(compactName)}"><span class="qil-weekly-rank" aria-hidden="true">0${index + 1}</span><img src="${escapeHtml(safeUrl(image.src || image.url))}"${image.srcset ? ` srcset="${escapeHtml(image.srcset)}"` : ''} sizes="(max-width:680px) 18vw, 90px" width="${Math.max(1,Number(image.width || 600))}" height="${Math.max(1,Number(image.height || 600))}" alt="${escapeHtml(compactName)}" loading="${index === 0 ? 'eager' : 'lazy'}" decoding="async" style="transform:none;transition:none;animation:none"><span class="qil-weekly-meta"><strong>${escapeHtml(brandName(product))}</strong><em>${escapeHtml(priceLabel(product))}</em></span></a>`;
	}

	function renderEditorialProducts(includeHero = true) {
		const shortlist = recommendationSet().products;
		const routineKeys = (goalPolicy()[state.goal] || []).filter(key => shortlist.some(product => goalEvidence(product)?.purposeKey === key)).slice(0,3);
		const hero = includeHero ? $('[data-qil-hero-products]') : null;
		if (hero) {
			const weekly = collectionProducts('weekly-best-sellers');
			const fallback = collectionProducts('best-sellers');
			const selected = [...weekly, ...fallback].filter((product, index, list) => list.findIndex(item => String(item.id) === String(product.id)) === index).slice(0, 3);
			hero.innerHTML = selected.map(weeklyHeroProductMarkup).join('');
		}
		const routine = $('[data-qil-routine-products]');
		if (routine) routine.innerHTML = routineKeys.map((key, index) => editorialProductMarkup(shortlist.find(product => goalEvidence(product)?.purposeKey === key), index, key)).join('');
	}

	function hydrateCategoryTiles() {
		const purposeKeys = {protein:'protein', creatine:'creatine', 'fat-burner':'fat_burner'};
		const usedProducts = new Set();
		$$('[data-qil-category]').forEach(tile => {
			const key = purposeKeys[plain(tile.dataset.qilCategory)];
			const flashProducts = tile.dataset.qilCategory === 'flash' ? products.filter(item => item?.promotion?.isFlash === true && primaryImages(item)[0]) : [];
			const product = key ? curatedPurposeProduct(key) : tile.dataset.qilCategory === 'offers' ? products.find(item => item?.price?.onSale && primaryImages(item)[0]) : (flashProducts.find(item => !usedProducts.has(String(item.id))) || flashProducts[0]);
			const source = primaryImages(product)[0];
			const icon = $('.qil-category-icon', tile);
			if (!product || !source || !icon) return;
			usedProducts.add(String(product.id));
			tile.dataset.qilCategoryProduct = String(product.id);
			const image = document.createElement('img');
			image.className = 'qil-category-packshot';
			image.src = safeUrl(source.src || source.url);
			if (source.srcset) image.srcset = source.srcset;
			image.sizes = '(max-width:680px) 48px, 64px';
			image.width = Math.max(1, Number(source.width || 600));
			image.height = Math.max(1, Number(source.height || 600));
			image.alt = '';
			image.setAttribute('aria-hidden', 'true');
			image.loading = 'lazy';
			image.decoding = 'async';
			image.style.cssText = 'width:100%;height:100%;object-fit:contain;display:block;transform:none;transition:none;animation:none';
			icon.replaceChildren(image);
		});
	}

	function upsertProducts(incoming) {
		if (!Array.isArray(incoming)) return [];
		const accepted = [];
		incoming.forEach(record => {
			if (!record || record.id === undefined || record.id === null) return;
			const key = String(record.id), existing = productIndex.get(key);
			if (existing) Object.assign(existing, record);
			else { products.push(record); productIndex.set(key, record); }
			accepted.push(productIndex.get(key));
		});
		return accepted;
	}

	function searchDropdownElements() {
		const panel = $('.wd-search-results');
		return {panel, content: panel?.querySelector('.wd-scroll-content') || null};
	}

	function searchResultsUrl(query) {
		try {
			const form = searchInput?.closest('form');
			const url = new URL(form?.action || config.shopUrl || window.location.href, window.location.href);
			url.searchParams.set('s', plain(query));
			url.searchParams.set('post_type', 'product');
			return safeUrl(url.href);
		} catch (_) {
			return safeUrl(config.shopUrl || window.location.href);
		}
	}

	function clearFallbackSearch(force = false) {
		const {panel, content} = searchDropdownElements();
		if (!panel || !content) return;
		if (force) content.replaceChildren();
		else content.querySelector('[data-qil-search-fallback]')?.remove();
		if (force || !content.children.length) panel.classList.remove('wd-opened');
		if (searchInput) searchInput.setAttribute('aria-expanded', panel.classList.contains('wd-opened') ? 'true' : 'false');
	}

	function fallbackSearchPriceMarkup(product) {
		const price = product?.price || {}, current = price.sale || price.current || price, regular = price.regular;
		if (hasVisibleSale(product)) return `<del>${priceEntryHtml(regular, product)}</del><ins>${priceEntryHtml(current, product)}</ins>`;
		return priceEntryHtml(current, product);
	}

	function fallbackSearchImageMarkup(product) {
		const image = primaryImages(product)[0];
		if (!image) return '<span aria-hidden="true">Q</span>';
		return `<img src="${escapeHtml(safeUrl(image.src || image.url))}"${image.srcset ? ` srcset="${escapeHtml(image.srcset)}"` : ''} width="${Math.max(1, Number(image.width || 600))}" height="${Math.max(1, Number(image.height || 600))}" alt="" loading="lazy" decoding="async" sizes="68px" style="transform:none;transition:none;animation:none">`;
	}

	function fallbackAllResultsMarkup(query) {
		return `<a class="wd-all-results" href="${escapeHtml(searchResultsUrl(query))}">${escapeHtml(t('viewAllResults'))}</a>`;
	}

	function renderFallbackSearchState(message, query, includeAllResults = false) {
		const {panel, content} = searchDropdownElements();
		if (!panel || !content) return;
		content.innerHTML = `<div data-qil-search-fallback><div class="wd-all-results" role="status">${escapeHtml(message)}</div>${includeAllResults ? fallbackAllResultsMarkup(query) : ''}</div>`;
		panel.classList.add('wd-opened');
		searchInput?.setAttribute('aria-expanded', 'true');
	}

	function setRemoteSearchResults(incoming, query, authoritative = false) {
		if (plain(searchInput?.value).slice(0,64).toLowerCase() !== plain(query).slice(0,64).toLowerCase()) return;
		const matches = upsertProducts(incoming).map((product,index)=>({product,index,score:indexedSearchScore(product,query)})).sort((a,b)=>b.score-a.score||a.index-b.index).slice(0,8).map(row=>row.product);
		const ids = matches.map(product => Number(product.id));
		window.QimiaShoppingContext?.search?.(query, ids);
		const {panel, content} = searchDropdownElements();
		state.searchPending = false;
		if (!panel || !content) return;
		const suggestions = matches.map(product => `<div class="wd-suggestion" data-product-id="${escapeHtml(product.id)}">
			<div class="wd-suggestion-thumb">${fallbackSearchImageMarkup(product)}</div>
			<div class="wd-suggestion-content"><h4 class="wd-entities-title">${escapeHtml(product.name || '')}</h4><p class="price">${fallbackSearchPriceMarkup(product)}</p></div>
			<a class="wd-fill" href="${escapeHtml(safeUrl(product.url))}" aria-label="${escapeHtml(product.name || '')}"></a>
		</div>`).join('');
		const empty = matches.length ? '' : `<div class="wd-all-results" role="status">${escapeHtml(t('noSearchResults'))}</div>`;
		content.innerHTML = `<div data-qil-search-fallback>${suggestions}${empty}${fallbackAllResultsMarkup(query)}</div>`;
		panel.classList.add('wd-opened');
		searchInput?.setAttribute('aria-expanded', 'true');
		if (authoritative) document.dispatchEvent(new CustomEvent('qimia:search-rendered', {detail:{query, products:ids}}));
		const signature = JSON.stringify([state.lang, config.currency, plain(query).toLowerCase(), ids]);
		if (authoritative && signature !== searchEventSignature) {
			searchEventSignature = signature;
			document.dispatchEvent(new CustomEvent('qimia:activity', {detail:{type:matches.length ? 'search_results' : 'search_no_results', query, products:ids, surface:'search', result_count:matches.length}}));
		}
	}

	function searchNormalizeText(value) {
		return plain(value)
			.normalize('NFKD')
			.replace(/[\u0300-\u036f]/g, '')
			.toLowerCase()
			.replace(/[أإآٱ]/g, 'ا')
			.replace(/ى/g, 'ي')
			.replace(/[\u064B-\u065F\u0670\u06D6-\u06ED]/g, '')
			.replace(/[^\p{L}\p{N}]+/gu, ' ')
			.replace(/\s+/g, ' ')
			.trim();
	}

	function searchCompactText(value) {
		return searchNormalizeText(value).replace(/[^\p{L}\p{N}]+/gu, '');
	}

	function searchHasWordPrefix(haystack, needle) {
		const text = searchNormalizeText(haystack), query = searchNormalizeText(needle);
		if (!text || !query) return false;
		return text === query || text.startsWith(`${query} `) || text.includes(` ${query}`);
	}

	function indexedSearchScore(product, query) {
		const normalized = searchNormalizeText(query);
		if (!normalized) return 0;
		const compact = searchCompactText(query);
		const title = searchNormalizeText(product?.name || '');
		const titleCompact = searchCompactText(product?.name || '');
		const brand = searchNormalizeText(product?.brand?.name || product?.brand || '');
		const sku = searchNormalizeText(product?.sku || '');
		const skuCompact = searchCompactText(product?.sku || '');
		const categories = categoryNames(product);
		const rawTerms = [
			...categories,
			...(Array.isArray(product?.tags) ? product.tags.map(item => plain(item?.name || item)) : []),
			...(Array.isArray(product?.match?.searchTokens) ? product.match.searchTokens : []),
			...(Array.isArray(product?.searchTokens) ? product.searchTokens : [])
		];
		const taxonomyText = searchNormalizeText(rawTerms.join(' '));
		const combined = searchNormalizeText([title, brand, taxonomyText].filter(Boolean).join(' '));
		const tokens = normalized.split(/\s+/).filter(Boolean);
		let score = 0;

		if (title === normalized) score = Math.max(score, 1600);
		else if (title.startsWith(normalized)) score = Math.max(score, 1450);
		else if (searchHasWordPrefix(title, normalized)) score = Math.max(score, 1280);
		else if (title.includes(normalized)) score = Math.max(score, 1160);
		if (compact.length >= 3 && titleCompact.includes(compact)) score = Math.max(score, 1040);

		if (brand === normalized) score = Math.max(score, 1380);
		else if (brand.startsWith(normalized)) score = Math.max(score, 1220);
		else if (searchHasWordPrefix(brand, normalized) || brand.includes(normalized)) score = Math.max(score, 1040);

		if (sku) {
			if (sku === normalized || (compact && skuCompact === compact)) score = Math.max(score, 1750);
			else if (sku.startsWith(normalized) || (compact && skuCompact.startsWith(compact))) score = Math.max(score, 1520);
			else if (compact.length >= 3 && skuCompact.includes(compact)) score = Math.max(score, 1340);
		}

		if (tokens.length) {
			const titleWords = title.split(/\s+/).filter(Boolean);
			if (tokens.every(token => titleWords.some(word => word.startsWith(token)))) score = Math.max(score, 980);
			if (tokens.every(token => combined.includes(token))) score = Math.max(score, 720);
			if (taxonomyText && tokens.every(token => taxonomyText.includes(token))) score = Math.max(score, 620);
			if (score < 520) {
				const corpus = searchNormalizeText(productCorpus(product));
				if (tokens.every(token => corpus.includes(token))) score = Math.max(score, 260);
			}
		}

		if (score > 0) {
			score += Math.min(36, Math.round(Math.log2(Math.max(1, Number(product?.salesCount || 0) + 1)) * 3));
			score += Math.min(10, Math.round(Number(product?.rating || 0) * 2));
		}
		return score;
	}

	function indexedSearchMatches(query) {
		const ranked = products
			.map((product, index) => ({product, index, score:indexedSearchScore(product, query)}))
			// Do not flash weak description-only matches before the live search
			// returns. Name/brand/SKU/category intent must be strong enough.
			.filter(entry => entry.score >= 500)
			.sort((left, right) => right.score - left.score || left.index - right.index);
		// Once a strong title/brand/SKU hit exists, hide weak taxonomy-only noise.
		const minimum = ranked[0]?.score >= 1000 ? 900 : 500;
		return ranked.filter(entry => entry.score >= minimum).slice(0, 8).map(entry => entry.product);
	}

	function renderIndexedSearchFallback(query) {
		const indexed = indexedSearchMatches(query);
		if (indexed.length) setRemoteSearchResults(indexed, query);
		else renderFallbackSearchState(t('searchUnavailable'), query, true);
	}

	const searchSelectionKey = () => JSON.stringify([state.lang, String(config.currency || '').toUpperCase(), plain(searchInput?.value).slice(0,64).toLowerCase()]);
	function cancelStoreSearch() {
		window.clearTimeout(searchTimer); searchTimer = 0;
		searchController?.abort(); searchController = null;
		searchRequestSerial++; searchPendingKey = '';
	}
	async function fetchStoreSearch(query) {
		if (!config.searchUrl || typeof window.fetch !== 'function') {
			state.searchPending = false; renderIndexedSearchFallback(query); return;
		}
		const key = searchSelectionKey();
		if (searchPendingKey === key) return;
		cancelStoreSearch();
		const known = searchResponses.get(key);
		if (known && Date.now() - known.at < 300000) { state.searchPending = false; setRemoteSearchResults(known.products, query, true); return; }
		const serial = searchRequestSerial, expectedCurrency = String(config.currency || '').toUpperCase();
		const controller = typeof AbortController === 'function' ? new AbortController() : null;
		searchController = controller; searchPendingKey = key; state.searchPending = true;
		let timedOut = false;
		const timeout = controller ? window.setTimeout(() => { timedOut = true; controller.abort(); }, 12000) : 0;
		try {
			const url = new URL(config.searchUrl, window.location.href);
			if (url.origin !== location.origin) throw new Error('search_origin');
			url.searchParams.set('q', query); url.searchParams.set('limit', '8');
			url.searchParams.set('qil_locale', state.lang === 'ar' ? 'ar' : 'en');
			if (expectedCurrency) url.searchParams.set('qimia_currency', expectedCurrency);
			const headers = languageHeaders({'Accept':'application/json'});
			if (config.restNonce) headers['X-WP-Nonce'] = config.restNonce;
			const response = await fetch(url.href, {method:'GET', credentials:'same-origin', cache:'no-store', headers, signal:controller?.signal});
			if (!response.ok) throw new Error(`search_${response.status}`);
			const payload = await response.json();
			if (serial !== searchRequestSerial || key !== searchSelectionKey()) return;
			if (!Array.isArray(payload?.products) || plain(payload.query).toLowerCase() !== plain(query).toLowerCase()) throw new Error('search_contract');
			if (payload.products.some(product => expectedCurrency && String(product?.price?.currency || '').toUpperCase() !== expectedCurrency)) throw new Error('search_currency');
			searchResponses.delete(key); searchResponses.set(key, {at:Date.now(), products:payload.products});
			if (searchResponses.size > 60) searchResponses.delete(searchResponses.keys().next().value);
			setRemoteSearchResults(payload.products, query, true);
		} catch (error) {
			if (serial !== searchRequestSerial || key !== searchSelectionKey() || (error?.name === 'AbortError' && !timedOut)) return;
			state.searchPending = false;
			if (!searchFailureShown) { searchFailureShown = true; showToast(t('searchUnavailable')); }
			renderIndexedSearchFallback(query);
		} finally {
			window.clearTimeout(timeout);
			if (serial === searchRequestSerial) { searchController = null; searchPendingKey = ''; state.searchPending = false; }
		}
	}

	function scheduleStoreSearch(query) {
		cancelStoreSearch();
		const normalized = plain(query).slice(0,64);
		if (normalized.length < 2) { state.searchPending = false; searchEventSignature = ''; clearFallbackSearch(true); return; }
		const immediate = indexedSearchMatches(normalized);
		if (normalized.length < SEARCH_REMOTE_MIN) {
			// Too short for a useful live query: answer from the page's own index.
			state.searchPending = false;
			if (immediate.length) setRemoteSearchResults(immediate, normalized);
			else renderFallbackSearchState(t('searchKeepTyping'), normalized, true);
			return;
		}
		if (immediate.length) setRemoteSearchResults(immediate, normalized);
		else renderFallbackSearchState(t('searchingStore'), normalized);
		searchTimer = window.setTimeout(() => { searchTimer = 0; void fetchStoreSearch(normalized); }, SEARCH_DEBOUNCE_MS);
	}

	let toastTimer;
	function hideToast() { const toast = $('[data-qil-toast]'); if (toast) { toast.hidden = true; toast.classList.remove('is-cart-confirmation'); toast.replaceChildren(); } }
	function showToast(message) { const toast = $('[data-qil-toast]'); if (!toast) return; toast.classList.remove('is-cart-confirmation'); toast.textContent = message; toast.hidden = false; clearTimeout(toastTimer); toastTimer = setTimeout(hideToast, 2400); }
	function showCartConfirmation(product) {
		const toast = $('[data-qil-toast]');
		if (!toast) return;
		const image = primaryImages(product)[0];
		toast.classList.add('is-cart-confirmation');
		toast.innerHTML = `<div class="qil-cart-confirmation">${image ? `<img src="${escapeHtml(safeUrl(image.src || image.url))}" width="44" height="44" alt="" loading="lazy" decoding="async">` : ''}<div><strong>${escapeHtml(t('cartAdded'))}</strong>${product?.name ? `<span>${escapeHtml(product.name)}</span>` : ''}</div><a href="${escapeHtml(safeUrl(config.cartUrl))}">${escapeHtml(t('viewCart'))}</a><button type="button" data-qil-toast-dismiss aria-label="${escapeHtml(t('dismiss'))}">×</button></div>`;
		toast.hidden = false;
		clearTimeout(toastTimer);
		toastTimer = setTimeout(hideToast, 3800);
	}
	function modalFocusables(modal) {
		return $$('a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])', modal)
			.filter(node => !node.hidden && node.getClientRects().length > 0);
	}
	function openModal(modal) {
		if (!modal) return;
		state.lastFocus = document.activeElement;
		// Inert siblings along the modal's own ancestor chain. A PDP dialog is
		// outside the header shell; inverting the whole content made it inert too.
		let branch = modal;
		while (branch && branch !== document.body && branch.parentElement) {
			for (const sibling of branch.parentElement.children) {
				if (sibling === branch || ['SCRIPT','STYLE','LINK','TEMPLATE'].includes(sibling.tagName) || sibling.inert) continue;
				sibling.inert = true; sibling.dataset.qilBodyModalInert = '1';
			}
			branch = branch.parentElement;
		}
		modal.hidden = false;
		modal.setAttribute('aria-hidden', 'false');
		document.documentElement.style.overflow = 'hidden';
		syncAILauncherOffset();
		setTimeout(() => modalFocusables(modal)[0]?.focus(), 20);
	}
	function closeModal(modal) {
		if (!modal) return;
		modal.hidden = true;
		modal.setAttribute('aria-hidden', 'true');
		$$('[data-qil-modal-inert]', root).forEach(child => { child.inert = false; delete child.dataset.qilModalInert; });
		document.querySelectorAll('[data-qil-body-modal-inert]').forEach(child => { child.inert = false; delete child.dataset.qilBodyModalInert; });
		document.documentElement.style.overflow = '';
		syncAILauncherOffset();
		if (state.lastFocus instanceof HTMLElement) state.lastFocus.focus();
	}
	function parsedCartCount(value) {
		if (typeof value === 'number' && Number.isFinite(value)) return Math.max(0, Math.floor(value));
		const normalized = String(value ?? '').replace(/[٠-٩]/g, digit => String('٠١٢٣٤٥٦٧٨٩'.indexOf(digit))).replace(/[۰-۹]/g, digit => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(digit)));
		const match = normalized.match(/\d+/);
		return match ? Math.max(0, Number.parseInt(match[0], 10) || 0) : null;
	}
	let cartHydrationTimer = 0, cartHydrationAttempts = 0, cartHydrationSettled = false;
	function wooItemsInCartCookieCount() {
		const prefix = 'woocommerce_items_in_cart=';
		const pair = String(document.cookie || '').split(';').map(value => value.trim()).find(value => value.startsWith(prefix));
		if (!pair) return 0;
		let value = pair.slice(prefix.length);
		try { value = decodeURIComponent(value); } catch (_) {}
		return parsedCartCount(value) || 0;
	}
	function miniCartContentsCount() {
		const panel = document.querySelector('.cart-widget-side');
		const content = panel?.querySelector('.widget_shopping_cart_content');
		if (!content) return null;
		const rows = [...content.querySelectorAll('.woocommerce-mini-cart-item, .mini_cart_item')];
		if (rows.length) {
			return rows.reduce((total, row) => {
				const inputQuantity = Number(row.querySelector('input.qty')?.value);
				if (Number.isFinite(inputQuantity) && inputQuantity > 0) return total + Math.floor(inputQuantity);
				const quantityText = plain(row.querySelector('.quantity')?.textContent);
				const match = quantityText.match(/(\d+)\s*(?:×|x)/i);
				return total + (match ? Math.max(1, Number.parseInt(match[1], 10) || 1) : 1);
			}, 0);
		}
		return content.querySelector('.woocommerce-mini-cart__empty-message') ? 0 : null;
	}
	function headerCartContentsCount() {
		const node = document.querySelector('[data-qil-cart-count], .qil-bag .wd-cart-number, .cart-widget-opener .wd-cart-number');
		return node ? parsedCartCount(node.textContent) : null;
	}
	function setCartCount(value) {
		const parsed = parsedCartCount(value);
		if (parsed === null) return false;
		state.cartCount = parsed;
		state.cartHydrated = true;
		document.querySelectorAll('[data-qil-cart-count]').forEach(node => {
			node.textContent = String(state.cartCount);
			node.hidden = state.cartCount === 0;
			node.classList.toggle('is-empty', state.cartCount === 0);
			node.setAttribute('aria-hidden', state.cartCount === 0 ? 'true' : 'false');
			node.setAttribute('role', 'status');
			node.setAttribute('aria-live', 'polite');
			node.setAttribute('aria-atomic', 'true');
			node.setAttribute('aria-label', state.lang === 'ar' ? `${state.cartCount} ${t('itemsInCart')}` : `${state.cartCount} ${t('itemsInCart')}`);
		});
		// WoodMart can replace the complete header-cart fragment and remove
		// QIL's data attribute. Its native count remains authoritative; only
		// mirror empty-state visibility here and preserve its localized markup.
		document.querySelectorAll('.qil-bag .wd-cart-number:not([data-qil-cart-count])').forEach(node => {
			node.hidden = state.cartCount === 0;
			node.classList.toggle('is-empty', state.cartCount === 0);
			node.setAttribute('aria-hidden', state.cartCount === 0 ? 'true' : 'false');
		});
		document.querySelectorAll('[data-qil-cart-summary-count]').forEach(node => {
			node.textContent = String(state.cartCount);
			node.hidden = state.cartCount === 0;
			node.classList.toggle('is-empty', state.cartCount === 0);
			node.setAttribute('aria-hidden', state.cartCount === 0 ? 'true' : 'false');
		});
		return true;
	}
	// The theme prints the free-delivery bar's width inline, but the message and
	// the width come from separate fragment updates and can disagree. Deriving
	// the goal from the theme's own two numbers keeps the fill, the percentage
	// and the wording exactly consistent, whatever order they arrive in.
	/* The mini-cart is WoodMart's, entirely. Its markup, its styling, its open
	   and close state and its free-delivery bar are the theme's to own; this
	   plugin only keeps its own launcher out of the way. Everything that used to
	   restyle or rewrite the drawer was removed in v0.30 — it was fighting the
	   theme and the drawer looked it. */
	function syncCartSummary() {
		syncAILauncherOffset();
	}
	function syncCartCount(preferredCount = null) {
		const preferred = parsedCartCount(preferredCount);
		const miniCartCount = miniCartContentsCount();
		const headerCount = headerCartContentsCount();
		const nextCount = preferred !== null ? preferred : (miniCartCount !== null ? miniCartCount : headerCount);
		const hydrated = nextCount !== null ? setCartCount(nextCount) : false;
		syncCartSummary();
		return hydrated;
	}
	function applyWooFragments(fragments) {
		if (!fragments || typeof fragments !== 'object') return false;
		const allowed = ['span[data-qil-cart-count]', 'div.widget_shopping_cart_content'];
		let applied = false, miniCartApplied = false, fragmentCount = null;
		allowed.forEach(selector => {
			const markup = fragments[selector];
			if (typeof markup !== 'string' || !markup) return;
			applied = true;
			if (selector === 'div.widget_shopping_cart_content') miniCartApplied = true;
			if (selector === 'span[data-qil-cart-count]') {
				const template = document.createElement('template');
				template.innerHTML = markup.trim();
				fragmentCount = parsedCartCount(template.content.firstElementChild?.textContent);
			}
			if (window.jQuery) window.jQuery(selector).replaceWith(markup);
			else document.querySelectorAll(selector).forEach(node => {
				const template = document.createElement('template');
				template.innerHTML = markup.trim();
				if (template.content.firstElementChild) node.replaceWith(template.content.firstElementChild.cloneNode(true));
			});
		});
		syncCartCount(fragmentCount);
		window.requestAnimationFrame(() => syncCartCount(fragmentCount));
		return applied && miniCartApplied;
	}
	function requestCartRefresh() {
		if (window.jQuery) window.jQuery(document.body).trigger('wc_fragment_refresh');
	}
	// A cached homepage can restore an old empty Woo fragment. Refresh from Woo
	// on an explicit bag open, even when WoodMart owns the drawer. No polling.
	let cartOpenCheckedAt = 0, cartOpenPending = false, cartOpenTimer = 0;
	function refreshCartOnOpen() {
		if (!window.jQuery || cartOpenPending || (cartOpenCheckedAt && Date.now() - cartOpenCheckedAt < 15000)) return;
		cartOpenPending = true;
		cartOpenCheckedAt = Date.now();
		const panel = document.querySelector('.cart-widget-side');
		panel?.setAttribute('aria-busy', 'true');
		requestCartRefresh();
		window.clearTimeout(cartOpenTimer);
		cartOpenTimer = window.setTimeout(finishCartOpenRefresh, 6500);
	}
	function finishCartOpenRefresh() {
		cartOpenPending = false;
		window.clearTimeout(cartOpenTimer);
		document.querySelector('.cart-widget-side')?.removeAttribute('aria-busy');
	}
	document.addEventListener('click', event => {
		if (event.button > 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
		if (event.target instanceof Element && event.target.closest('.cart-widget-opener > a, .qil-bag > a')) refreshCartOnOpen();
	}, {capture:true, passive:true});
	window.addEventListener('pageshow', event => {
		if (!event.persisted) return;
		cartOpenCheckedAt = 0;
		if (document.querySelector('.cart-widget-side.wd-opened')) refreshCartOnOpen();
	});
	function cartNeedsHydrationRecovery() {
		return wooItemsInCartCookieCount() > 0 && miniCartContentsCount() === 0;
	}
	function cancelCartHydrationRecovery(settled = false) {
		window.clearTimeout(cartHydrationTimer);
		cartHydrationTimer = 0;
		if (settled) cartHydrationSettled = true;
	}
	function scheduleCartHydrationRecovery(delay = 900) {
		if (cartHydrationSettled || cartHydrationTimer || cartHydrationAttempts >= 2 || !window.jQuery || !cartNeedsHydrationRecovery()) return;
		cartHydrationTimer = window.setTimeout(() => {
			cartHydrationTimer = 0;
			if (cartHydrationSettled || cartHydrationAttempts >= 2 || !cartNeedsHydrationRecovery()) return;
			cartHydrationAttempts += 1;
			requestCartRefresh();
		}, Math.max(0, Number(delay) || 0));
	}
	function handleCartFragmentsHydrated(event) {
		// Restored browser fragments are not a fresh server result. Do not let an
		// empty cached fragment cancel recovery when the Woo cart cookie is nonempty.
		const staleEmpty = event?.type === 'wc_fragments_loaded' && cartNeedsHydrationRecovery();
		cancelCartHydrationRecovery(!staleEmpty);
		if (staleEmpty) scheduleCartHydrationRecovery(250);
		syncCartCount();
		syncCartSummary();
	}
	function handleCartFragmentsAjaxError() {
		if (useNativeWoodmartCart) return;
		if (cartHydrationSettled || !cartNeedsHydrationRecovery() || cartHydrationAttempts >= 2) return;
		cancelCartHydrationRecovery(false);
		// A failed core hydration becomes the guarded first attempt; a failed
		// guarded attempt receives only one bounded retry.
		scheduleCartHydrationRecovery(cartHydrationAttempts === 0 ? 250 : 650);
	}
	let currencySyncTimer = 0;
	function handleQimiaCurrencyChange() {
        clearRepeatPrivate(); inventorySeen.clear();
		window.clearTimeout(currencySyncTimer);
		currencySyncTimer = window.setTimeout(() => {
			cancelStoreSearch();
			cancelGoalLoad();
			goalPending = false;
			quickViewCache.clear();
			remoteSearchRanks.clear();
			searchResponses.clear();
			searchController?.abort();
			quickViewController?.abort();
			variationController?.abort();
			requestCartRefresh();
			window.requestAnimationFrame(() => { syncCartCount(); syncCartSummary(); });
		}, 0);
	}
	async function removeMiniCartItem(link) {
		let key = plain(
			link?.getAttribute?.('data-cart_item_key')
			|| link?.dataset?.cart_item_key
			|| link?.dataset?.cartItemKey
		);
		if (!key) {
			try { key = plain(new URL(link?.href || '', window.location.href).searchParams.get('remove_item')); } catch (_) { key = ''; }
		}
		const endpoint = wcAjaxEndpoint('remove_from_cart');
		if (!link || link.dataset.qilRemoving === '1') return false;
		if (!key || !endpoint) {
			const fallback = safeUrl(link.href);
			if (fallback) window.location.assign(fallback);
			return false;
		}
		const controller = typeof AbortController === 'function' ? new AbortController() : null;
		link.dataset.qilRemoving = '1';
		link.setAttribute('aria-disabled', 'true');
		const row = link.closest('.woocommerce-mini-cart-item, .mini_cart_item');
		row?.classList.add('is-removing');
		try {
			const body = new URLSearchParams({cart_item_key:key});
			const response = await fetch(endpoint, {
				method:'POST', credentials:'same-origin', signal:controller?.signal,
				headers:languageHeaders({'Accept':'application/json','Content-Type':'application/x-www-form-urlencoded; charset=UTF-8','X-Requested-With':'XMLHttpRequest'}),
				body:body.toString()
			});
			if (!response.ok) throw new Error(`cart_remove_${response.status}`);
			const payload = await response.json();
			if (!payload || payload.success === false) throw new Error('cart_remove_rejected');
			const fragments = payload.fragments && typeof payload.fragments === 'object' ? payload.fragments : {};
			const miniCartReady = applyWooFragments(fragments);
			if (window.jQuery) window.jQuery(document.body).trigger('removed_from_cart', [fragments, plain(payload.cart_hash), window.jQuery(link)]);
			if (!miniCartReady) requestCartRefresh();
			window.requestAnimationFrame(() => { syncCartCount(); syncCartSummary(); });
			return true;
		} catch (error) {
			if (error?.name === 'AbortError') return false;
			const fallback = safeUrl(link.href);
			if (fallback) window.location.assign(fallback);
			return false;
		} finally {
			delete link.dataset.qilRemoving;
			link.removeAttribute('aria-disabled');
			row?.classList.remove('is-removing');
		}
	}
	/* Opening the drawer means adding the one class WoodMart itself adds, and
	   nothing else. Everything this used to set — inert, aria-hidden, its own
	   expanded/collapsed states — was this plugin second-guessing the theme. */
	function openWoodmartCart(expand = true, focusDetails = false) {
		const panel = document.querySelector('.cart-widget-side');
		if (!panel) return;
		panel.classList.add('wd-opened');
		document.querySelector('.wd-close-side')?.classList.add('wd-close-side-opened');
		syncAILauncherOffset();
		if (focusDetails) {
			window.setTimeout(() => {
				const target = modalFocusables(panel)[0] || panel;
				try { target?.focus({preventScroll:true}); } catch (_) { target?.focus?.(); }
			}, 0);
		}
	}
	function closeFallbackCart() {
		const panel = document.querySelector('.cart-widget-side');
		if (!panel) return;
		panel.classList.remove('wd-opened');
		document.querySelector('.wd-close-side')?.classList.remove('wd-close-side-opened');
		syncAILauncherOffset();
	}
	/* The drawer's accessibility state is WoodMart's own; setting inert here
	   fought the theme when it opened the drawer its own way. */
	function observeCartDrawerAccessibility() {
		syncAILauncherOffset();
	}
	function stabilizeAddedToCartButton(buttonReference) {
		const candidate = buttonReference?.jquery ? buttonReference.get(0) : buttonReference;
		const button = candidate instanceof Element ? candidate : $('.qil-buy.loading');
		window.setTimeout(() => {
			const card = button?.closest('.qil-product-card');
			(card ? $$('a.added_to_cart.wc-forward', card) : $$('a.added_to_cart.wc-forward')).forEach(link => link.remove());
			if (!button) return;
			button.classList.remove('loading', 'added');
			button.classList.add('qil-added');
		}, 0);
	}
	function registerCartSuccess(event, fragments, cartHash, buttonReference) {
		const candidate = buttonReference?.jquery ? buttonReference.get(0) : buttonReference;
		const button = candidate instanceof Element ? candidate : $('.qil-buy.loading');
		const productId = button?.dataset.qilParentId || button?.closest('.qil-product-card')?.dataset.productId || button?.dataset.product_id;
		const product = actionProduct(String(productId || ''));
		const quickViewModal = button?.closest('[data-qil-quick-view-modal]');
		if (quickViewModal) closeQuickView(quickViewModal);
		const receivedFragments = useNativeWoodmartCart ? false : applyWooFragments(fragments || event?.detail?.fragments);
		window.setTimeout(syncCartCount, 0);
		stabilizeAddedToCartButton(buttonReference || button);
		$$('.qil-buy.loading').forEach(button => button.classList.remove('loading'));
		if (useNativeWoodmartCart) return;
		if (!receivedFragments) requestCartRefresh();
		const now = Date.now();
		if (now - state.lastCartEvent < 700) return;
		state.lastCartEvent = now;
		showCartConfirmation(product);
		window.setTimeout(openWoodmartCart, 80);
	}
	function registerCartUpdate(event, fragments) {
		if (!useNativeWoodmartCart) applyWooFragments(fragments);
		window.setTimeout(syncCartCount, 0);
	}

	let revealObserver;
	function observeReveals() {
		if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) { $$('.qil-reveal:not(.is-visible)').forEach(el => el.classList.add('is-visible')); return; }
		if (!revealObserver) revealObserver = new IntersectionObserver(entries => entries.forEach(entry => { if (entry.isIntersecting) { entry.target.classList.add('is-visible'); revealObserver.unobserve(entry.target); } }), {rootMargin:'0px 0px -7%', threshold:.08});
		$$('.qil-reveal:not(.is-visible)').forEach(el => revealObserver.observe(el));
	}

	function setupHeroScrollMotion() {
		// Floating remains in CSS. No scroll handler publishes inherited
		// custom properties across the hero on every animation frame.
		const selector = '.qil-label-stack.has-multiple,[data-qil-portal],.qil-ai-art,[data-qil-stack-scene],.qil-collection-skeleton';
		const groups = new Map();
		const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
		const update = (node, inView) => {
			const value = inView && !document.hidden && !reduceMotion.matches ? 'running' : 'paused';
			if (node.dataset.qilMotion !== value) node.dataset.qilMotion = value;
		};
		const observer = 'IntersectionObserver' in window ? new IntersectionObserver(entries => {
			entries.forEach(entry => {
				if (!groups.has(entry.target)) return;
				groups.set(entry.target, entry.isIntersecting);
				update(entry.target, entry.isIntersecting);
			});
		}, {threshold:0}) : null;
		const register = node => {
			if (groups.has(node)) return;
			// CSS starts at the first paused frame, so deferred/added cards
			// show a label immediately without briefly animating offscreen.
			groups.set(node, false);
			update(node, false);
			observer?.observe(node);
		};
		const discover = node => {
			if (!(node instanceof Element)) return;
			if (node.matches(selector)) register(node);
			node.querySelectorAll(selector).forEach(register);
		};
		const scopes = shells().filter(shell => !shell.parentElement?.closest('.qil-shell,[data-qil-shell]'));
		if (!scopes.includes(root) && !root.closest('.qil-shell,[data-qil-shell]')) scopes.push(root);
		scopes.forEach(discover);
		const mutations = new MutationObserver(records => {
			let removed = false;
			records.forEach(record => {
				record.addedNodes.forEach(discover);
				if (record.removedNodes.length) removed = true;
			});
			if (removed) groups.forEach((inView, node) => {
				if (!node.isConnected) { observer?.unobserve(node); groups.delete(node); }
			});
		});
		scopes.forEach(scope => mutations.observe(scope, {childList:true, subtree:true}));
		const sync = () => groups.forEach((inView, node) => update(node, inView));
		document.addEventListener('visibilitychange', sync);
		if (typeof reduceMotion.addEventListener === 'function') reduceMotion.addEventListener('change', sync);
		else if (typeof reduceMotion.addListener === 'function') reduceMotion.addListener(sync);
	}


	function followHomeSection(event) {
		const link = event.target instanceof Element ? event.target.closest('.qil-header a[href]') : null;
		if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
		let url; try { url = new URL(link.href, location.href); } catch (_) { return; }
		if (!['#qil-match','#qil-ai','#qil-compare'].includes(url.hash)) return;
		const samePage = url.origin === location.origin && url.pathname.replace(/\/$/,'') === location.pathname.replace(/\/$/,'');
		const target = samePage ? document.getElementById(url.hash.slice(1)) : null;
		if (!target) return; // Follow the real localized home URL normally.
		event.preventDefault();
		target.scrollIntoView({behavior:matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',block:'start'});
		history.replaceState(null,'',url.href);
	}
	document.addEventListener('click', followHomeSection);

	delegate.addEventListener('pointerover', prefetchQuickViewFromEvent, {passive:true});
	delegate.addEventListener('focusin', prefetchQuickViewFromEvent);
	delegate.addEventListener('touchstart', prefetchQuickViewFromEvent, {passive:true});
	delegate.addEventListener('click', event => {
		const element = event.target instanceof Element ? event.target : null; if (!element) return;
		const cartOpener = !useNativeWoodmartCart && element.closest('.cart-widget-opener > a');
		if (cartOpener) { event.preventDefault(); event.stopPropagation(); openWoodmartCart(true, true); return; }
		if (element.closest('[data-qil-toast-dismiss]')) { event.preventDefault(); hideToast(); return; }
		const railArrow = element.closest('[data-qil-rail-prev], [data-qil-rail-next]');
		if (railArrow) {
			event.preventDefault();
			const rail = railFor(railArrow);
			if (rail) scrollRail(rail, railArrow.hasAttribute('data-qil-rail-prev') ? -1 : 1);
			return;
		}
		const quickViewClose = element.closest('[data-qil-qv-close], [data-qil-qv-backdrop]');
		if (quickViewClose) { event.preventDefault(); closeQuickView(quickViewClose.closest('[data-qil-quick-view-modal]')); return; }
		const disabledQuickAdd = element.closest('[data-qil-qv-add][aria-disabled="true"]');
		if (disabledQuickAdd) { event.preventDefault(); return; }
		// The open modal carries data-qil-quick-view-id so its cache can be reused.
		// Never treat a click inside it as a request to re-open it: doing so
		// re-rendered the panel and detached the resolved Add button before
		// WooCommerce's delegated handler could add the variation to the cart.
		const insideQuickView = element.closest('[data-qil-quick-view-modal]');
		const quickViewTrigger = insideQuickView ? null : element.closest('[data-qil-quick-view-id]');
		if (
			quickViewTrigger
			&& !quickViewTrigger.hasAttribute('data-qil-quick-view-modal')
			&& 0 === event.button
			&& !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey
		) {
			event.preventDefault();
			trackSessionIntent('product', quickViewTrigger.dataset.qilQuickViewId);
			void openQuickView(quickViewTrigger.dataset.qilQuickViewId);
			return;
		}
		const nudgeDismiss = element.closest('[data-qil-nudge-dismiss]');
		if (nudgeDismiss) { event.preventDefault(); dismissIntentNudge(); return; }
		const heroGoal = element.closest('[data-qil-hero-ai-chip][data-goal]');
		if (heroGoal) {
			event.preventDefault();
			const form = $('[data-qil-hero-ai-form]');
			const input = form ? $('[data-qil-hero-ai-input]', form) : null;
			const suggestedPrompt = plain(heroGoal.dataset.qilAiPrompt).slice(0, 600);
			if (input && suggestedPrompt) input.value = suggestedPrompt;
			setHeroAIStatus('');
			// Merge the fast local goal engine with the live AI path: the catalogue
			// responds immediately even if the assistant is still booting.
			setGoal(plain(heroGoal.dataset.goal), false);
			if (form && suggestedPrompt) void submitHeroAIQuery(form);
			return;
		}
		const aiTrigger = element.closest('[data-qil-ai-intent]');
		if (aiTrigger) { if (aiTrigger.closest('[data-qil-intent-nudge]')) dismissIntentNudge(); void handleQimiaAIIntent(aiTrigger); return; }
		const compareButton = element.closest('[data-compare-id]');
		if (compareButton) { event.preventDefault(); toggleCompare(compareButton.dataset.compareId); return; }
		const goal = element.closest('.qil-shell [data-goal]'); if (goal) return setGoal(goal.dataset.goal, true);
		const prefButton = element.closest('[data-pref]');
		if (prefButton) { const pref = prefButton.dataset.pref; if (!['stimfree','vegan','value'].includes(pref)) return; event.preventDefault(); state.prefs.has(pref) ? state.prefs.delete(pref) : state.prefs.add(pref); syncGoalFilters(); state.visible = 8; goalTouched = true; invalidateRecommendations(); queueGoalPage(); refreshRecommendationSurfaces(); document.dispatchEvent(new CustomEvent('qil:filters-changed')); return; }
		const productLink = element.closest('.qil-product-card a[href]');
		if (productLink) trackSessionIntent('product', productLink.closest('.qil-product-card')?.dataset.productId);
		const buyButton = element.closest('.qil-buy.ajax_add_to_cart'); if (buyButton) buyButton.classList.add('loading');
	});
	delegate.addEventListener('submit', event => {
		const form = event.target instanceof Element ? event.target.closest('[data-qil-hero-ai-form]') : null;
		if (!form) return;
		event.preventDefault();
		void submitHeroAIQuery(form);
	});
	const heroAIInput = $('[data-qil-hero-ai-input]');
	if (heroAIInput) heroAIInput.maxLength = 600;
	delegate.addEventListener('change', event => {
		const select = event.target instanceof Element ? event.target.closest('[data-qil-variation-attribute]') : null;
		if (!select) return;
		// The product page renders the same option fields inline, so the engine
		// takes whichever container holds them rather than only the modal.
		const modal = select.closest('[data-qil-quick-view-modal], [data-qil-variation-panel]');
		if (modal) { refreshQuickViewOptions(modal, select.dataset.attributeKey); resetQuickViewVariation(modal); scheduleVariationCheck(modal); }
	});

	const searchbar = $('[data-qil-searchbar]'), searchInput = $('[data-qil-search]');
	if (searchInput) {
		// QIL owns this one search field end-to-end. Remove WoodMart's duplicate
		// clear control and detach a pre-existing autocomplete instance if a
		// global theme bundle initialized it before the staging page script.
		$('.wd-clear-search')?.remove();
		searchInput.closest('form')?.classList.remove('woodmart-ajax-search');
		searchInput.classList.remove('wd-search-inited');
		if (window.jQuery && typeof window.jQuery.fn?.devbridgeAutocomplete === 'function') {
			try { window.jQuery(searchInput).devbridgeAutocomplete('dispose'); } catch (_) { /* No native instance was attached. */ }
			window.jQuery(searchInput).removeData('autocomplete');
		}
		searchInput.maxLength = 64;
		const searchResults = searchDropdownElements().panel;
		if (searchResults) {
			if (!searchResults.id) searchResults.id = 'qil-search-results';
			searchInput.setAttribute('aria-controls', searchResults.id);
			searchInput.setAttribute('aria-expanded', searchResults.classList.contains('wd-opened') ? 'true' : 'false');
		}
		searchInput.addEventListener('input', event => {
			event.stopImmediatePropagation();
			scheduleStoreSearch(event.target.value);
		}, true);
	}
	
	$('[data-qil-show-more]')?.addEventListener('click', () => {
		if (goalError) { void loadGoalPage(goalRetryPage || 1); return; }
		if (state.visible >= recommendationSet().products.length && goalHasMore) { state.visible += 8; void loadGoalPage(goalPage + 1); return; }
		state.visible += 8; renderProducts();
	});
	delegate.addEventListener('click', event => {
		if (!(event.target instanceof Element) || !event.target.closest('[data-qil-clear-filters]')) return;
		state.prefs.clear(); state.search = ''; state.visible = 8; if (searchInput) searchInput.value = '';
		$$('[data-pref]').forEach(button => {button.classList.remove('is-active');button.setAttribute('aria-pressed','false');});
		invalidateRecommendations(); queueGoalPage(); refreshRecommendationSurfaces();
        document.dispatchEvent(new CustomEvent('qil:filters-changed'));
	});
	const compareModal = $('[data-qil-compare-modal]');
	// Use the same native AI intent bridge as the product assistant. The
	// assistant's own delegated listener still boots its lazy runtime.
	$$('[data-qil-open-compare]').forEach(button => {
		button.setAttribute('data-qimia-ai-open', '');
		button.dataset.qilAiIntent = 'compare-picker';
		button.addEventListener('click', () => {
			button.dataset.qilAiIntent = state.compare.length === 2 ? 'compare-selected' : 'compare-picker';
			if (state.compare.length === 2) window.QimiaShoppingContext?.compare?.(state.compare.map(p => Number(p.id)));
		});
	});
	$$('[data-qil-modal-close]').forEach(button => button.addEventListener('click', () => closeModal(button.closest('.qil-modal'))));

	delegate.addEventListener('click', event => {
		const target = event.target instanceof Element ? event.target.closest('[data-qil-menu-open], [data-qil-search-toggle], [data-qil-search-close]') : null;
		if (!target || !root.contains(target)) return;
		event.preventDefault(); event.stopPropagation();
		if (target.matches('[data-qil-menu-open]')) { const nativeMenuTrigger = document.querySelector('.asmm-trigger'); if (nativeMenuTrigger) nativeMenuTrigger.click(); return; }
		if (target.matches('[data-qil-search-toggle]')) { searchbar.hidden = false; searchInput?.focus(); return; }
		const closeSearch = target.matches('[data-qil-search-close]');
		cancelStoreSearch();
		state.searchPending = false;
		state.search = '';
		remoteSearchRanks.clear();
		if (searchInput) {
			searchInput.value = '';
			searchInput.dispatchEvent(new Event('input', {bubbles:true}));
		}
		clearFallbackSearch(true);
		if (closeSearch) searchbar.hidden = true;
		else searchInput?.focus();
	}, true);

	// Language/currency navigation is owned by independent qil-navigation.js.

	const localeControl = $('.qil-locale-control');
	if (localeControl) {
		document.addEventListener('pointerdown', event => {
			if (localeControl.open && event.target instanceof Node && !localeControl.contains(event.target)) localeControl.open = false;
		}, {passive:true});
	}

	document.addEventListener('keydown', event => {
		const activeModal = $('.qil-modal:not([hidden])');
		if (event.key === 'Escape') {
			if (localeControl?.open) localeControl.open = false;
			if (searchbar && !searchbar.hidden) {
				cancelStoreSearch();
				if (searchInput) {
					searchInput.value = '';
					searchInput.dispatchEvent(new Event('input', {bubbles:true}));
				}
				clearFallbackSearch(true);
				searchbar.hidden = true;
				$('[data-qil-search-toggle]')?.focus();
			}
			const cartPanel = !useNativeWoodmartCart && document.querySelector('.cart-widget-side.wd-opened');
			if (cartPanel) closeFallbackCart();
			$$('.qil-modal:not([hidden])').forEach(modal => modal.matches('[data-qil-quick-view-modal]') ? closeQuickView(modal) : closeModal(modal));
			return;
		}
		const focusScope = activeModal;
		if (event.key !== 'Tab' || !focusScope) return;
		const focusables = modalFocusables(focusScope);
		if (!focusables.length) { event.preventDefault(); return; }
		const first = focusables[0], last = focusables[focusables.length - 1];
		if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
		else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
	});
	document.addEventListener('click', event => {
		if (useNativeWoodmartCart) return;
		const link = event.target instanceof Element
			? event.target.closest('.widget_shopping_cart_content a.remove_from_cart_button, .widget_shopping_cart_content a.remove[data-cart_item_key]')
			: null;
		if (
			!link || typeof window.fetch !== 'function'
			|| (typeof event.button === 'number' && event.button !== 0)
			|| event.metaKey || event.ctrlKey || event.shiftKey || event.altKey
		) return;
		event.preventDefault();
		event.stopImmediatePropagation();
		void removeMiniCartItem(link);
	}, true);
	document.addEventListener('click', event => {
		if (useNativeWoodmartCart) return;
		const element = event.target instanceof Element ? event.target : null;
		const openerLink = element?.closest('.cart-widget-opener > a');
		if (
			openerLink
			&& ! event.defaultPrevented
			&& 0 === event.button
			&& ! event.metaKey && ! event.ctrlKey && ! event.shiftKey && ! event.altKey
			&& document.querySelector('.cart-widget-side')
		) {
			event.preventDefault();
			event.stopPropagation();
			openWoodmartCart(true, true);
			return;
		}
		const close = element?.closest('.close-side-widget, .wd-close-side');
		if (!close) return;
		event.preventDefault();
		event.stopPropagation();
		closeFallbackCart();
	});
	// Only the no-theme fallback owns outside-click dismissal. WoodMart handles
	// this path itself whenever its registered cart modules are available.
	document.addEventListener('pointerdown', event => {
		if (useNativeWoodmartCart) return;
		const panel = document.querySelector('.cart-widget-side.wd-opened');
		if (!panel || !(event.target instanceof Node)) return;
		if (panel.contains(event.target)) return;
		if (event.target instanceof Element && event.target.closest('.cart-widget-opener, .cart-widget-side')) return;
		closeFallbackCart();
	}, {passive:true});
	if (window.jQuery) {
		window.jQuery(document.body)
			.on('added_to_cart', registerCartSuccess)
			.on('removed_from_cart', registerCartUpdate)
			.on('wc_fragments_refreshed wc_fragments_loaded', handleCartFragmentsHydrated)
			.on('wc_fragments_ajax_error', handleCartFragmentsAjaxError)
			.on('wc_fragments_refreshed wc_fragments_ajax_error', finishCartOpenRefresh)
			.on('qimia_currency_changed.qil', handleQimiaCurrencyChange);
	}
	document.body.addEventListener('wc-blocks_added_to_cart', registerCartSuccess);
	document.addEventListener('qimia_currency_changed', handleQimiaCurrencyChange);
	const compareLayoutQuery = window.matchMedia('(max-width: 760px)');
	if (typeof compareLayoutQuery.addEventListener === 'function') compareLayoutQuery.addEventListener('change', () => syncCompareLayoutMode());
	else if (typeof compareLayoutQuery.addListener === 'function') compareLayoutQuery.addListener(() => syncCompareLayoutMode());


    // Bounded presentation bridge: one existing card renderer and exact-item
    // registry. No persistent customer storage. Also used by the 1.18 sales
    // sections, so it no longer depends on the personalization switch;
    // qil-personalization.js keeps its own personalizationEnabled guard.
    window.QILCards = Object.freeze({
        version: 2,
        render: productCard,
        // Public catalogue records delivered with a section join the page index,
        // so Quick View, Compare and Qimia AI resolve them like any other card.
        upsert: records => upsertProducts(Array.isArray(records) ? records.slice(0, 48) : []),
        product: id => actionProduct(String(id)) || null,
        // Reuse public catalogue records already delivered for this page.
        // Never read purchase history or fetch another catalogue for guests.
        publicSelections(excluded = []) {
            const omit = new Set(excluded.map(Number));
            return products.filter(product => {
                if (omit.has(Number(product.id)) || product?.stock?.inStock !== true
                    || product?.purchase?.purchasable !== true || !(Number(product?.price?.value) > 0)
                    || String(product?.price?.currency) !== String(config.currency) || !primaryImages(product).length) return false;
                if (state.prefs.has('vegan') && !explicitDietary(product, 'vegan')) return false;
                if (state.prefs.has('stimfree') && !explicitDietary(product, 'stimfree')) return false;
                if (state.prefs.has('value') && (product?.price?.perServingVerified !== true || !(Number(product.price.perServing) > 0))) return false;
                return true;
            }).slice(0, 12);
        },
        hydrate(records, rows, nonce) {
            repeatProducts = Array.isArray(records) ? records.slice(0,24) : [];
            repeatPurchases.clear();
            const inventory = {};
            repeatProducts.forEach(product => {
                if (!product || !(Number(product.id) > 0) || !product.inventory || typeof product.inventory !== 'object') return;
                const id = String(Number(product.id)); inventoryLive.set(id, product.inventory); inventorySeen.add(id); inventory[id] = product.inventory;
            });
            (Array.isArray(rows) ? rows.slice(0,24) : []).forEach(row => {
                if (row && row.canAdd === true) repeatPurchases.set(`${row.orderId}:${row.itemId}`, row);
                const id = Number(row?.variationId || row?.productId || 0);
                if (id > 0 && row?.inventory && typeof row.inventory === 'object') {
                    inventoryLive.set(String(id), row.inventory); inventorySeen.add(String(id)); inventory[String(id)] = row.inventory;
                }
            });
            if (Object.keys(inventory).length) inventoryStoreWrite(inventory);
            repeatNonce = typeof nonce === 'string' ? nonce : '';
            renderLiveInventory();
        },
        clear() { repeatProducts = []; repeatNonce = ''; repeatPurchases.clear(); },
        filters: () => [...state.prefs].filter(p => ['stimfree','vegan','value'].includes(p)).sort(),
        context: () => ({compare: state.compare.map(p => Number(p.id)).slice(0,3)}),
        refresh: () => { setupRails(); observeReveals(); renderLiveInventory(); }
    });
    // Sections that render with the shared card may load before or after this file.
    document.dispatchEvent(new CustomEvent('qil:cards-ready'));

	const productTotal = $('[data-qil-product-total]');
	const brandTotal = $('[data-qil-brand-total]');
	if (productTotal && Number(config.catalogueCount) > 0) productTotal.textContent = String(config.catalogueCount);
	if (brandTotal && Number(config.brandCount) > 0) brandTotal.textContent = String(config.brandCount);
	observeCartDrawerAccessibility();
	syncThemeToolbarOffset();
	window.addEventListener('orientationchange', () => { window.setTimeout(() => { syncThemeToolbarOffset(); syncAILauncherOffset(); }, 250); }, {passive:true});
	window.setTimeout(syncThemeToolbarOffset, 1200);
	setupAILauncherOffset();
	setupHeroScrollMotion();
	setupThemeAjaxLanguage();
	setupChromeOffset();
	setupProductGallery();
	setupProductBuyBar();
	void initProductPanel();
	setupRails();
	window.addEventListener('resize', () => $$('[data-qil-rail]').forEach(syncRailNav), {passive:true});
	if (!syncCartCount() && config.cartCount !== undefined && config.cartCount !== null) setCartCount(config.cartCount);
	syncCartSummary();
	scheduleCartHydrationRecovery();
	applyLanguage();
    setupRepeatPurchase();
	const routedGoal = new URL(location.href).searchParams.get('qil_goal');
	if (routedGoal && goalLabels()[routedGoal] && $('[data-qil-results]')) setGoal(routedGoal, false);
	observeReveals();
})();

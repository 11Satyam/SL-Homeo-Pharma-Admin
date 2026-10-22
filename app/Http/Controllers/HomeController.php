<?php

namespace App\Http\Controllers;

class HomeController extends Controller
{
    private function products(): array
    {
        return [
            [
                'id' => 'acne-cure-drop',
                'name' => 'Acne Cure Drop',
                'category' => 'Skin Care',
                'image' => asset('public/img/products/acne-cure-drop.png'),
                'benefits' => ['Pimples and acne', 'Blackheads and whiteheads', 'Oily skin', 'Acne marks', 'Recurrent skin eruptions', 'Facial redness and inflammation', 'Improves skin texture', 'Purifies blood and detoxifies skin'],
                'composition' => ['Berberis Aquifolium Q', 'Calendula Officinalis Q', 'Kali Bromatum 6X', 'Calcarea Carbonica 6X', 'Mangifera Indica 3X', 'Pulsatilla Nigricans 3X', 'Thuja Occidentalis 3X'],
                'dosage' => '20-25 drops in half cup water, 3 times daily or as directed',
                'certifications' => ['ISO', 'GMP', '100% Natural'],
            ],
            [
                'id' => 'gas-care-drop',
                'name' => 'Gas Care Drop',
                'category' => 'Digestive Care',
                'image' => asset('public/img/products/gas-care-drop.png'),
                'benefits' => ['Acidity and gas', 'Indigestion', 'Burning sensation in stomach and throat', 'Bloating and heaviness', 'Abdominal discomfort', 'Belching and sour eructations', 'Nausea', 'Hyperacidity and acid reflux'],
                'composition' => ['Colchicum', 'Carbo Vegetabilis', 'Natrum Phosphoricum', 'Nux Vomica', 'Robinia Pseudacacia'],
                'dosage' => '15-20 drops in 1/4 cup water, 3 times daily before meals or as directed',
                'certifications' => ['ISO', 'GMP', '100% Natural'],
            ],
            [
                'id' => 'cld-drop',
                'name' => 'CLD Drop',
                'category' => 'Cholesterol / Heart Care',
                'image' => asset('public/img/products/cld-drop.png'),
                'benefits' => ['Elevated cholesterol levels', 'Lipid metabolism support', 'Plaque formation prevention', 'Healthy blood circulation', 'Heart disease risk factors'],
                'composition' => ['Cholesterinum 3X', 'Cynara Scolymus 3X', 'Equisetum Hyemale 3X', 'Leonurus Cardiaca 3X', 'Solidago Virgaurea 3X', 'Taraxacum Q'],
                'dosage' => '20-25 drops in half cup water, 3 times daily or as directed',
                'certifications' => ['ISO', 'GMP', '100% Natural'],
            ],
            [
                'id' => 'diabo-cure-drop',
                'name' => 'Diabo Cure Drop',
                'category' => 'Diabetes Care',
                'image' => asset('public/img/products/diabo-cure-drop.png'),
                'benefits' => ['Frequent urination', 'Leakage or dribbling', 'Incomplete bladder emptying', 'Burning sensation while urinating', 'Blood sugar management support'],
                'composition' => ['Abroma Augusta 2X', 'Acidum Phosphoricum 6X', 'Cephalandra Indica 2X', 'Chionanthus Virginica 2X', 'Crataegus Oxyacantha 2X', 'Gymnema Sylvestre 3X', 'Helonias Dioica 3X', 'Syzygium Jambolanum Q'],
                'dosage' => '20-25 drops in half cup water, 3 times daily or as directed',
                'certifications' => ['ISO', 'GMP', '100% Natural'],
            ],
            [
                'id' => 'diabo-set-syrup',
                'name' => 'Diabo Set Syrup',
                'category' => 'Diabetes Care',
                'image' => asset('public/img/products/diabo-set-syrup.png'),
                'benefits' => ['Healthy blood sugar levels', 'Pancreas function support', 'Frequent urination', 'Excessive thirst', 'Weakness and fatigue', 'Numbness in body', 'Delayed wound healing', 'Dizziness'],
                'composition' => ['Abroma Augusta Q', 'Acidum Phosphoricum 3X', 'Cephalandra Indica Q', 'Chionanthus Virginica 3X', 'Gymnema Sylvestre Q', 'Helonias Dioica 3X', 'Syzygium Jambolanum Q'],
                'dosage' => '20-30 ml with half cup water, 3 times daily or as directed',
                'certifications' => ['ISO', 'GMP', '100% Natural'],
            ],
            [
                'id' => 'fever-drop',
                'name' => 'Fever Drop',
                'category' => 'Fever / Cold Care',
                'image' => asset('public/img/products/fever-drop.png'),
                'benefits' => ['Body heat with chills', 'Pain in bones with soreness', 'Coryza and sore throat', 'Muscular pain', 'Headache', 'Fever-related discomfort'],
                'composition' => ['Arsenicum Album', 'Baptisia Tinctoria', 'Eucalyptus Globulus', 'Eupatorium Perfoliatum', 'Gelsemium Sempervirens', 'Ocimum Sanctum'],
                'dosage' => '20-30 drops, 3-4 times daily or as directed',
                'certifications' => ['ISO', 'GMP', '100% Natural'],
            ],
            [
                'id' => 'gastro-z-plus-syrup',
                'name' => 'Gastro-Z Plus Syrup',
                'category' => 'Digestive Care',
                'image' => asset('public/img/products/gastro-z-plus-syrup.png'),
                'benefits' => ['Acidity relief', 'Heartburn', 'Healthy digestion support', 'Sour eructations', 'Burning in chest', 'Indigestion'],
                'composition' => ['Asafoetida 3X', 'Carbo Vegetabilis 6X', 'Hydrastis Canadensis Q', 'Lycopodium Clavatum Q', 'Natrum Phosphoricum 6X', 'Nux Vomica Q', 'Piper Nigra 3X'],
                'dosage' => '20-30 drops, 3-4 times daily or as directed',
                'certifications' => ['ISO', 'GMP', '100% Natural'],
            ],
            [
                'id' => 'migraino-cure-drop',
                'name' => 'Migraino Cure Drop',
                'category' => 'Headache / Migraine Care',
                'image' => asset('public/img/products/migraino-cure-drop.png'),
                'benefits' => ['Migraine headaches', 'Neuralgic head pain', 'Stress-induced headaches', 'Frequent or continuous headaches', 'Throbbing pain and nausea', 'Light sensitivity in migraine'],
                'composition' => ['Gelsemium Sempervirens 3X', 'Glonoinum 6X', 'Ignatia Amara Q', 'Iris Versicolor Q', 'Sanguinaria Canadensis 3X', 'Secale Cornutum 3X', 'Spigelia 3X'],
                'dosage' => '20-30 drops, 3-4 times daily or as directed',
                'certifications' => ['ISO', 'GMP', '100% Natural'],
            ],
            [
                'id' => 'prosto-cure-drop',
                'name' => 'Prosto Cure Drop',
                'category' => 'Prostate / Urinary Care',
                'image' => asset('public/img/products/prosto-cure-drop.png'),
                'benefits' => ['Frequent urination', 'Leakage or dribbling', 'Incomplete bladder emptying', 'Burning sensation while urinating', 'Prostate health support'],
                'composition' => ['Chimaphila Umbellata 3X', 'Clematis Erecta 3X', 'Pareira Brava 3X', 'Sabal Serrulata Q'],
                'dosage' => '20-25 drops in half cup water, 3 times daily or as directed',
                'certifications' => ['ISO', 'GMP', '100% Natural'],
            ],
            [
                'id' => 'uterine-fibro-drop',
                'name' => 'Uterine Fibro Drop',
                'category' => "Women's Health",
                'image' => asset('public/img/products/uterine-fibro-drop.png'),
                'benefits' => ['Uterine fibroids', 'Abnormal uterine bleeding', 'Pelvic pressure', 'Hard mass in lower abdomen', 'Constipation and GI symptoms', 'Fibroid-related discomfort'],
                'composition' => ['Acidum Aceticum 6X', 'Calcarea Arsenicosa 6X', 'Calcarea Iodatum 6X', 'Conium Maculatum 6X', 'Hydrastis Canadensis 3X', 'Iodium 6X', 'Lachesis 6X', 'Lapis Albus 3X', 'Secale Cornutum 3X', 'Silicea 6X'],
                'dosage' => '20-25 drops in half cup water, 3 times daily or as directed',
                'certifications' => ['ISO', 'GMP', '100% Natural'],
            ],
            [
                'id' => 'rheuma-win-syrup',
                'name' => 'Rheuma Win Syrup',
                'category' => 'Joint Pain / Arthritis',
                'image' => asset('public/img/4.jpg.jpeg'),
                'benefits' => ['Joint pain', 'Arthritis', 'Rheumatism', 'Swelling and stiffness in knees', 'Pain while walking/moving', 'Pain during weather changes', 'Back pain', 'Morning stiffness', 'Joint pain due to increased uric acid'],
                'composition' => ['Acidum Formicum', 'Bryonia Alba', 'Cimicifuga Racemosa', 'Colchicum', 'Gelsemium Sempervirens', 'Ledum Palustre', 'Piper Methysticum', 'Rhus Toxicodendron'],
                'dosage' => '25-30 drops 3-4 times daily or as directed',
                'certifications' => ['ISO', 'GMP'],
            ],
            [
                'id' => 'piles-drop',
                'name' => 'Piles Drop',
                'category' => 'Piles / Hemorrhoids',
                'image' => asset('public/img/3.jpg.jpeg'),
                'benefits' => ['Bleeding piles', 'Burning and pain in anus', 'Swelling of piles', 'Constipation-related piles', 'Pain after stool', 'Itching in anal region', 'External and internal hemorrhoids', 'Engorged veins relief', 'Incarcerated piles'],
                'composition' => ['Aesculus Hippocastanum', 'Calcarea Fluorica', 'Collinsonia Canadensis', 'Hamamelis Virginiana', 'Lycopodium Clavatum', 'Paeonia Officinalis', 'Ratanhia', 'Sulphur'],
                'dosage' => '25-30 drops 3-4 times daily or as directed',
                'certifications' => ['ISO', 'GMP'],
            ],
            [
                'id' => 'rehuma-plus-drop',
                'name' => 'Rehuma Plus Drop',
                'category' => 'Bone & Joint Care',
                'image' => asset('public/img/2.jpg.jpeg'),
                'benefits' => ['Bone Pain', 'Injury', 'Sprain', 'Swelling', 'Paralysis', 'Arthritis', 'Stiffness'],
                'composition' => ['Bryonia Alba', 'Calcarea Phosphorica', 'Causticum', 'Ledum Palustre', 'Lycopodium Clavatum', 'Rhododendron', 'Rhus Toxicodendron'],
                'dosage' => '25-30 drops 3-4 times daily or as directed',
                'certifications' => ['ISO', 'GMP'],
            ],
            [
                'id' => 'sciatico-cure-drop',
                'name' => 'Sciatico Cure Drop',
                'category' => 'Nerve Pain / Sciatica',
                'image' => asset('public/img/1.jpg.jpeg'),
                'benefits' => ['Back Pain', 'Nerve Pain', 'Leg Pain', 'Numbness', 'Tingling'],
                'composition' => ['Bryonia Alba', 'Colocynthis', 'Gnaphalium Polycephalum', 'Magnesium Phosphoricum', 'Rhus Toxicodendron', 'Viscum Album'],
                'dosage' => '25-30 drops 3-4 times daily or as directed',
                'certifications' => ['ISO', 'GMP'],
            ],
            [
                'id' => 'eczema-drops',
                'name' => 'Eczema Drops',
                'category' => 'Skin Care',
                'image' => asset('public/img/6.jpg.jpeg'),
                'benefits' => ['Dry eczema', 'Chronic skin issues', 'Itching relief', 'Burning sensation relief', 'Redness reduction', 'Swelling control'],
                'composition' => ['Arsenicum Album', 'Causticum', 'Graphites', 'Mercurius Solubilis', 'Rhus Toxicodendron', 'Sulphur'],
                'dosage' => '25-30 drops 3-4 times daily or as directed',
                'certifications' => ['ISO', 'GMP'],
            ],
            [
                'id' => 'stone-cure-drop',
                'name' => 'Stone Cure Drop',
                'category' => 'Kidney Stone / Urinary Care',
                'image' => asset('public/img/5.jpg.jpeg'),
                'benefits' => ['Kidney stone pain', 'Burning urination', 'Frequent urination', 'Lower abdominal pain', 'Back/side pain', 'Helps in stone-related discomfort'],
                'composition' => ['Berberis Vulgaris', 'Calcarea Carbonica', 'Hydrangea Arborescens', 'Lycopodium Clavatum', 'Mangifera Indica', 'Thuja Occidentalis'],
                'dosage' => '25-30 drops 3-4 times daily or as directed',
                'certifications' => ['ISO', 'GMP'],
            ],
            [
                'id' => 'spondilex-cure-drop',
                'name' => 'Spondilex Cure Drop',
                'category' => 'Neck Pain / Cervical Spondylitis',
                'image' => asset('public/img/7.jpg.jpeg'),
                'benefits' => ['Neck pain', 'Stiffness', 'Radiating pain to shoulders and arms', 'Numbness and tingling', 'Nerve compression relief', 'Dizziness and headache (associated cases)'],
                'composition' => ['Arnica Montana', 'Bryonia Alba', 'Calcarea Phosphorica', 'Hypericum Perforatum', 'Rhus Toxicodendron', 'Ruta Graveolens', 'Symphytum Officinale'],
                'dosage' => '25-30 drops 3-4 times daily or as directed',
                'certifications' => ['ISO', 'GMP'],
            ],
        ];
    }

    public function index()
    {
        return view('home', ['products' => $this->products()]);
    }

    public function about()
    {
        return view('about');
    }

    public function contact()
    {
        return view('contact');
    }

    public function service()
    {
        return view('service');
    }

    public function project()
    {
        return view('project', ['products' => $this->products()]);
    }

    public function terms()
    {
        return view('terms');
    }

    public function privacy()
    {
        return view('privacy');
    }

    public function video()
    {
        return view('video');
    }

    public function faq()
    {
        return view('faq');
    }

    public function productDetail(string $id)
    {
        $products = $this->products();
        $product = collect($products)->firstWhere('id', $id);

        if (! $product) {
            abort(404);
        }

        return view('product-detail', compact('product', 'products'));
    }
}

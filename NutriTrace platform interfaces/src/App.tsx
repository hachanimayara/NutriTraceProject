import { useMemo, useState, type ReactNode } from "react";

type Page =
  | "home"
  | "catalogue"
  | "compare"
  | "trace"
  | "login"
  | "dashboard"
  | "users"
  | "farms"
  | "products"
  | "transformations"
  | "lots"
  | "distributions"
  | "stores"
  | "certifications"
  | "indicators"
  | "ai";

type IconName =
  | "leaf"
  | "grid"
  | "users"
  | "farm"
  | "box"
  | "shuffle"
  | "truck"
  | "award"
  | "spark"
  | "search"
  | "bell"
  | "plus"
  | "arrow"
  | "check"
  | "warning"
  | "clock"
  | "more"
  | "menu"
  | "close"
  | "download"
  | "filter"
  | "eye"
  | "edit"
  | "trash"
  | "map"
  | "logout";

const iconPaths: Record<IconName, ReactNode> = {
  leaf: <><path d="M20 4C12 4 5 8 5 15c0 2.2 1.8 4 4 4 7 0 11-7 11-15Z" /><path d="M4 21c2.5-5 6-8 11-11" /></>,
  grid: <><rect x="3" y="3" width="7" height="7" rx="2" /><rect x="14" y="3" width="7" height="7" rx="2" /><rect x="3" y="14" width="7" height="7" rx="2" /><rect x="14" y="14" width="7" height="7" rx="2" /></>,
  users: <><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" /></>,
  farm: <><path d="m3 11 9-7 9 7" /><path d="M5 10v10h14V10M9 20v-6h6v6" /><path d="M4 7V3h3" /></>,
  box: <><path d="m21 8-9-5-9 5 9 5 9-5Z" /><path d="m3 8 9 5 9-5M3 8v8l9 5 9-5V8M12 13v8" /></>,
  shuffle: <><path d="M16 3h5v5M4 20 21 3M21 16v5h-5M15 15l6 6M4 4l5 5" /></>,
  truck: <><path d="M10 17h4V5H2v12h3M14 9h4l4 4v4h-3" /><circle cx="7.5" cy="17.5" r="2.5" /><circle cx="16.5" cy="17.5" r="2.5" /></>,
  award: <><circle cx="12" cy="8" r="5" /><path d="M8.5 12 7 22l5-3 5 3-1.5-10" /><path d="m10 8 1.3 1.3L14 6.7" /></>,
  spark: <><path d="m12 3-1.5 4.5L6 9l4.5 1.5L12 15l1.5-4.5L18 9l-4.5-1.5L12 3Z" /><path d="m5 15-.8 2.2L2 18l2.2.8L5 21l.8-2.2L8 18l-2.2-.8L5 15ZM19 14l-.7 1.3L17 16l1.3.7L19 18l.7-1.3L21 16l-1.3-.7L19 14Z" /></>,
  search: <><circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" /></>,
  bell: <><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" /></>,
  plus: <><path d="M12 5v14M5 12h14" /></>,
  arrow: <><path d="m9 18 6-6-6-6" /></>,
  check: <><path d="m5 12 4 4L19 6" /></>,
  warning: <><path d="M10.3 3.6 2.5 18a2 2 0 0 0 1.8 3h15.4a2 2 0 0 0 1.8-3L13.7 3.6a2 2 0 0 0-3.4 0Z" /><path d="M12 9v4M12 17h.01" /></>,
  clock: <><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></>,
  more: <><circle cx="5" cy="12" r="1" /><circle cx="12" cy="12" r="1" /><circle cx="19" cy="12" r="1" /></>,
  menu: <><path d="M4 7h16M4 12h16M4 17h16" /></>,
  close: <><path d="m6 6 12 12M18 6 6 18" /></>,
  download: <><path d="M12 3v12M7 10l5 5 5-5M4 21h16" /></>,
  filter: <><path d="M4 5h16M7 12h10M10 19h4" /></>,
  eye: <><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" /><circle cx="12" cy="12" r="2.5" /></>,
  edit: <><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z" /></>,
  trash: <><path d="M4 7h16M9 7V4h6v3M7 7l1 14h8l1-14M10 11v6M14 11v6" /></>,
  map: <><path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3Z" /><path d="M9 3v15M15 6v15" /></>,
  logout: <><path d="M10 17l5-5-5-5M15 12H3M15 4h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-4" /></>,
};

function Icon({ name, size = 18 }: { name: IconName; size?: number }) {
  return <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">{iconPaths[name]}</svg>;
}

function Logo({ compact = false }: { compact?: boolean }) {
  return <div className="logo"><div className="logo-mark"><Icon name="leaf" size={21} /></div>{!compact && <div><strong>NutriTrace</strong><span>Transparence alimentaire</span></div>}</div>;
}

function Button({ children, icon, variant = "primary", onClick, type = "button", disabled = false }: { children: ReactNode; icon?: IconName; variant?: "primary" | "secondary" | "ghost" | "danger"; onClick?: () => void; type?: "button" | "submit"; disabled?: boolean }) {
  return <button type={type} className={`btn btn-${variant}`} onClick={onClick} disabled={disabled}>{icon && <Icon name={icon} size={17} />}{children}</button>;
}

function Badge({ children, tone = "green" }: { children: ReactNode; tone?: "green" | "orange" | "red" | "blue" | "gray" }) {
  return <span className={`badge badge-${tone}`}><span className="badge-dot" />{children}</span>;
}

function Field({ label, placeholder, type = "text", options }: { label: string; placeholder?: string; type?: string; options?: string[] }) {
  return <label className="field"><span>{label}</span>{options ? <select defaultValue=""><option value="" disabled>{placeholder || "Sélectionner"}</option>{options.map((option) => <option key={option}>{option}</option>)}</select> : <input type={type} placeholder={placeholder} />}</label>;
}

function Modal({ title, children, onClose, wide = false }: { title: string; children: ReactNode; onClose: () => void; wide?: boolean }) {
  return <div className="modal-layer" onMouseDown={onClose}><div className={`modal ${wide ? "modal-wide" : ""}`} onMouseDown={(event) => event.stopPropagation()}><div className="modal-head"><div><span className="eyebrow">NutriTrace</span><h2>{title}</h2></div><button className="icon-btn" onClick={onClose} aria-label="Fermer"><Icon name="close" /></button></div>{children}</div></div>;
}

const productPhoto = "https://images.unsplash.com/photo-1656898882128-9abaea910371?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixlib=rb-4.1.0&q=80&w=1080";
const fieldPhoto = "https://images.unsplash.com/photo-1469806580075-c8309682eaeb?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixlib=rb-4.1.0&q=80&w=1080";

const navGroups: { label: string; items: { page: Page; label: string; icon: IconName }[] }[] = [
  { label: "Vue globale", items: [{ page: "dashboard", label: "Tableau de bord", icon: "grid" }, { page: "users", label: "Utilisateurs", icon: "users" }] },
  { label: "Production", items: [{ page: "farms", label: "Exploitations", icon: "farm" }, { page: "products", label: "Produits", icon: "box" }] },
  { label: "Traçabilité", items: [{ page: "transformations", label: "Transformations", icon: "shuffle" }, { page: "lots", label: "Lots", icon: "box" }, { page: "distributions", label: "Distributions", icon: "truck" }, { page: "stores", label: "Points de vente", icon: "map" }] },
  { label: "Transparence", items: [{ page: "certifications", label: "Certifications", icon: "award" }, { page: "indicators", label: "Impact environnemental", icon: "leaf" }, { page: "ai", label: "NutriTrace AI", icon: "spark" }] },
];

const entityConfigs: Partial<Record<Page, { title: string; subtitle: string; singular: string; relation: string; icon: IconName; columns: string[]; rows: string[][]; stats: [string, string][] }>> = {
  users: { title: "Utilisateurs", subtitle: "Gérez les accès, rôles et comptes professionnels.", singular: "utilisateur", relation: "Rôle et organisation", icon: "users", columns: ["Utilisateur", "Rôle", "Organisation", "Statut", "Création"], rows: [["Claire Martin", "Producteur", "Ferme des Trois Chênes", "Actif", "12 sept. 2024"], ["Mehdi Laurent", "Transformateur", "Atelier Saveurs", "Actif", "08 oct. 2024"], ["Sophie Bernard", "Distributeur", "Bio Marché Lyon", "En attente", "21 nov. 2024"], ["Luc Robert", "Consommateur", "—", "Actif", "04 déc. 2024"], ["Anna Dupont", "Administrateur", "NutriTrace", "Actif", "15 août 2024"]], stats: [["1 284", "Utilisateurs"], ["48", "Comptes en attente"], ["86%", "Comptes actifs"]] },
  farms: { title: "Exploitations agricoles", subtitle: "Suivez les origines, pratiques et producteurs responsables.", singular: "exploitation", relation: "Produits associés", icon: "farm", columns: ["Exploitation", "Localisation", "Agriculture", "Producteur", "Statut"], rows: [["Ferme des Trois Chênes", "Drôme, France", "Biologique", "Claire Martin", "Active"], ["Domaine du Val Vert", "Isère, France", "Raisonnée", "Julien Morel", "Active"], ["Maraîchers de Loire", "Loiret, France", "Biologique", "Lina Petit", "À vérifier"], ["Vergers du Soleil", "Vaucluse, France", "Conventionnelle", "Paul Rey", "Active"]], stats: [["47", "Exploitations"], ["128", "Produits liés"], ["72%", "Pratiques durables"]] },
  products: { title: "Produits", subtitle: "Gérez le catalogue et reliez chaque produit à son exploitation.", singular: "produit", relation: "Exploitation d’origine", icon: "box", columns: ["Produit", "Référence", "Catégorie", "Exploitation", "Statut"], rows: [["Tomates anciennes", "PRD-2025-042", "Légumes", "Ferme des Trois Chênes", "Publié"], ["Jus de pomme artisanal", "PRD-2025-038", "Boissons", "Vergers du Soleil", "Publié"], ["Farine de blé T80", "PRD-2025-031", "Céréales", "Domaine du Val Vert", "Brouillon"], ["Courgettes bio", "PRD-2025-029", "Légumes", "Maraîchers de Loire", "Publié"]], stats: [["128", "Produits"], ["12", "À compléter"], ["84%", "Traçabilité complète"]] },
  transformations: { title: "Transformations", subtitle: "Contrôlez les opérations, rendements et justificatifs.", singular: "transformation", relation: "Produit et lots sortants", icon: "shuffle", columns: ["Référence", "Opération", "Produit", "Rendement", "Statut"], rows: [["TRF-2025-089", "Pressage à froid", "Pommes Gala", "82%", "Terminée"], ["TRF-2025-088", "Mise en conserve", "Tomates anciennes", "91%", "En cours"], ["TRF-2025-084", "Mouture", "Blé tendre", "76%", "À vérifier"], ["TRF-2025-079", "Conditionnement", "Courgettes bio", "98%", "Terminée"]], stats: [["64", "Transformations"], ["87%", "Rendement moyen"], ["3", "Anomalies détectées"]] },
  lots: { title: "Lots", subtitle: "Retracez les lots entrants, sortants et leurs filiations.", singular: "lot", relation: "Transformation et lot parent", icon: "box", columns: ["N° de lot", "Produit", "Transformation", "Quantité", "Statut"], rows: [["LOT-TOM-2506-A", "Tomates anciennes", "TRF-2025-088", "820 kg", "Disponible"], ["LOT-JUS-2505-C", "Jus de pomme", "TRF-2025-089", "410 L", "En distribution"], ["LOT-BLE-2505-B", "Farine T80", "TRF-2025-084", "680 kg", "À vérifier"], ["LOT-CRG-2506-A", "Courgettes bio", "Conditionnement", "540 kg", "Disponible"]], stats: [["236", "Lots actifs"], ["91%", "Origine complète"], ["8", "À vérifier"]] },
  distributions: { title: "Distributions", subtitle: "Suivez les mouvements des lots jusqu’aux points de vente.", singular: "distribution", relation: "Lot et point de vente", icon: "truck", columns: ["Référence", "Lot", "Destination", "Expédition", "Statut"], rows: [["DIS-2506-108", "LOT-TOM-2506-A", "Bio Marché Lyon", "18 juin 2025", "Expédiée"], ["DIS-2506-104", "LOT-JUS-2505-C", "Épicerie du Parc", "17 juin 2025", "Reçue"], ["DIS-2506-101", "LOT-CRG-2506-A", "Les Halles Vertes", "16 juin 2025", "En retard"], ["DIS-2506-097", "LOT-BLE-2505-B", "Coopérative Centrale", "14 juin 2025", "Préparée"]], stats: [["89", "Mouvements actifs"], ["7", "En transit"], ["2", "Retards"]] },
  stores: { title: "Points de vente", subtitle: "Gérez les destinations et leurs informations de réception.", singular: "point de vente", relation: "Distributions reçues", icon: "map", columns: ["Point de vente", "Type", "Ville", "Responsable", "Statut"], rows: [["Bio Marché Lyon", "Magasin bio", "Lyon", "Sophie Bernard", "Actif"], ["Épicerie du Parc", "Épicerie", "Grenoble", "Nadia Benali", "Actif"], ["Les Halles Vertes", "Marché couvert", "Valence", "Marc Faure", "Actif"], ["Coopérative Centrale", "Coopérative", "Chambéry", "Alice Roux", "À vérifier"]], stats: [["32", "Points de vente"], ["14", "Villes couvertes"], ["96%", "Réceptions validées"]] },
  certifications: { title: "Certifications", subtitle: "Vérifiez les justificatifs, statuts et dates d’expiration.", singular: "certification", relation: "Produit certifié", icon: "award", columns: ["Certification", "Produit", "Organisme", "Expiration", "Statut"], rows: [["Agriculture Biologique", "Tomates anciennes", "Ecocert", "12 mai 2026", "Vérifiée"], ["Commerce Équitable", "Café des Andes", "Fairtrade", "28 fév. 2026", "En attente"], ["Label régional", "Farine T80", "Région AURA", "15 oct. 2025", "À renouveler"], ["Agriculture Biologique", "Courgettes bio", "Bureau Veritas", "09 juin 2026", "Vérifiée"]], stats: [["84", "Certifications"], ["12", "En attente"], ["5", "Expirent bientôt"]] },
  indicators: { title: "Impact environnemental", subtitle: "Documentez les mesures, méthodes et sources vérifiables.", singular: "indicateur", relation: "Produit mesuré", icon: "leaf", columns: ["Indicateur", "Produit", "Valeur", "Source", "Statut"], rows: [["Émissions CO₂e", "Tomates anciennes", "0,42 kg/kg", "Analyse ACV 2025", "Vérifié"], ["Consommation d’eau", "Jus de pomme", "42 L/L", "Relevé producteur", "À vérifier"], ["Distance parcourue", "Courgettes bio", "84 km", "Données transport", "Vérifié"], ["Énergie consommée", "Farine T80", "0,18 kWh/kg", "Atelier Saveurs", "Incomplet"]], stats: [["196", "Mesures"], ["68%", "Données vérifiées"], ["18", "Sources manquantes"]] },
};

function PublicHeader({ page, go }: { page: Page; go: (page: Page) => void }) {
  return <header className="public-header"><Logo /><nav><button className={page === "home" ? "active" : ""} onClick={() => go("home")}>Accueil</button><button className={page === "catalogue" ? "active" : ""} onClick={() => go("catalogue")}>Produits</button><button className={page === "trace" ? "active" : ""} onClick={() => go("trace")}>Traçabilité</button><button className={page === "compare" ? "active" : ""} onClick={() => go("compare")}>Comparateur</button></nav><div className="header-actions"><Button variant="ghost" onClick={() => go("login")}>Connexion</Button><Button onClick={() => go("catalogue")}>Explorer</Button></div></header>;
}

function Home({ go }: { go: (page: Page) => void }) {
  return <div className="public"><PublicHeader page="home" go={go} /><main>
    <section className="hero"><div className="hero-copy"><Badge tone="green">La transparence, de la ferme à l’assiette</Badge><h1>Connaissez vraiment l’histoire de ce que vous mangez.</h1><p>NutriTrace rassemble origine, transformations, certifications et impact environnemental dans un parcours clair et vérifiable.</p><div className="trace-search"><Icon name="search" /><input placeholder="Rechercher un produit ou un numéro de lot" /><Button onClick={() => go("trace")}>Retracer</Button></div><div className="hero-proof"><span><Icon name="check" /> Données sourcées</span><span><Icon name="check" /> Certifications vérifiées</span><span><Icon name="check" /> Analyse responsable</span></div></div><div className="hero-visual"><img src={fieldPhoto} alt="Vue aérienne d’une exploitation agricole" /><div className="floating-card top"><div className="mini-icon"><Icon name="leaf" /></div><div><strong>Origine confirmée</strong><span>Drôme, France</span></div><Icon name="check" /></div><div className="floating-card bottom"><span className="score">84%</span><div><strong>Traçabilité complète</strong><span>6 étapes documentées</span></div></div></div></section>
    <section className="journey"><div className="section-heading"><span className="eyebrow">Un parcours lisible</span><h2>Chaque étape compte</h2><p>Une vision continue qui relie tous les acteurs de la chaîne alimentaire.</p></div><div className="journey-grid">{[["farm","Production","Origine, récolte et pratiques agricoles"],["shuffle","Transformation","Opérations, rendements et lots"],["truck","Distribution","Mouvements et réceptions"],["award","Transparence","Certifications et impacts"]].map(([icon,title,text], index) => <div className="journey-step" key={title}><span className="step-number">0{index + 1}</span><div className="journey-icon"><Icon name={icon as IconName} /></div><h3>{title}</h3><p>{text}</p>{index < 3 && <span className="journey-line" />}</div>)}</div></section>
    <section className="featured"><div className="section-heading row"><div><span className="eyebrow">Produits documentés</span><h2>À découvrir cette semaine</h2></div><Button variant="secondary" onClick={() => go("catalogue")}>Voir le catalogue <Icon name="arrow" /></Button></div><ProductCards go={go} /></section>
    <section className="ai-band"><div className="ai-orb"><Icon name="spark" size={34} /></div><div><span className="eyebrow">NutriTrace AI</span><h2>Comprendre les données, sans les surinterpréter.</h2><p>Notre assistant synthétise les informations disponibles, signale les éléments à vérifier et indique toujours ses sources et limites.</p></div><Button onClick={() => go("login")}>Découvrir l’assistant</Button></section>
  </main><Footer /></div>;
}

const products = [
  { name: "Tomates anciennes", category: "Légumes", origin: "Drôme, France", producer: "Ferme des Trois Chênes", cert: "Agriculture Biologique", completeness: "Traçabilité complète" },
  { name: "Jus de pomme artisanal", category: "Boissons", origin: "Vaucluse, France", producer: "Vergers du Soleil", cert: "Label régional", completeness: "5 étapes sur 6" },
  { name: "Farine de blé T80", category: "Céréales", origin: "Isère, France", producer: "Domaine du Val Vert", cert: "En vérification", completeness: "4 étapes sur 6" },
];

function ProductCards({ go }: { go: (page: Page) => void }) {
  return <div className="product-grid">{products.map((product, index) => <article className="product-card" key={product.name}><div className="product-img"><img src={index === 1 ? fieldPhoto : productPhoto} alt="" /><span>{product.category}</span></div><div className="product-body"><div className="product-title"><h3>{product.name}</h3><Badge tone={index === 2 ? "orange" : "green"}>{index === 2 ? "À vérifier" : "Vérifié"}</Badge></div><p><Icon name="map" /> {product.origin}</p><p className="muted">Par {product.producer}</p><div className="product-meta"><span><Icon name="award" /> {product.cert}</span><span><Icon name="shuffle" /> {product.completeness}</span></div><Button variant="secondary" onClick={() => go("trace")}>Voir le parcours <Icon name="arrow" /></Button></div></article>)}</div>;
}

function Catalogue({ go }: { go: (page: Page) => void }) {
  return <div className="public subpage"><PublicHeader page="catalogue" go={go} /><main className="public-content"><div className="catalogue-head"><div><span className="eyebrow">Catalogue transparent</span><h1>Explorez les produits</h1><p>Découvrez leur origine et la disponibilité réelle de leurs données.</p></div><div className="catalogue-count"><strong>128</strong><span>produits documentés</span></div></div><div className="filter-bar"><div className="search-control"><Icon name="search" /><input placeholder="Produit, origine ou producteur..." /></div><select><option>Toutes les catégories</option><option>Légumes</option><option>Boissons</option><option>Céréales</option></select><select><option>Toutes les origines</option><option>Drôme</option><option>Isère</option></select><Button variant="secondary" icon="filter">Filtres</Button></div><ProductCards go={go} /><ProductCards go={go} /></main><Footer /></div>;
}

function Traceability({ go, embedded = false }: { go: (page: Page) => void; embedded?: boolean }) {
  const content = <div className={embedded ? "" : "public-content"}><div className="trace-head"><Badge>LOT-TOM-2506-A</Badge><h1>Tomates anciennes</h1><p>Parcours documenté du 12 juin au 19 juin 2025</p><div className="trace-summary"><span><Icon name="check" /> 6 étapes documentées</span><span><Icon name="award" /> Certificat vérifié</span><span><Icon name="leaf" /> 3 indicateurs disponibles</span></div></div><div className="trace-layout"><div className="timeline-card"><div className="card-head"><div><span className="eyebrow">De la ferme à l’assiette</span><h2>Parcours du produit</h2></div><Button variant="secondary" icon="download">Fiche PDF</Button></div><div className="timeline">{[
    ["farm","Production agricole","12 juin 2025 · 06:30","Ferme des Trois Chênes, Drôme","Récolte de 1 200 kg — agriculture biologique"],
    ["box","Création du produit","12 juin 2025 · 14:10","PRD-2025-042","Contrôle qualité initial validé"],
    ["shuffle","Transformation","14 juin 2025 · 09:20","TRF-2025-088","Tri et conditionnement — rendement 91%"],
    ["box","Création du lot","14 juin 2025 · 16:45","LOT-TOM-2506-A","820 kg disponibles"],
    ["truck","Distribution","18 juin 2025 · 07:15","DIS-2506-108","Expédition vers Bio Marché Lyon"],
    ["map","Point de vente","19 juin 2025 · 10:30","Bio Marché Lyon","Réception confirmée — 810 kg"],
  ].map(([icon,title,date,ref,text], index) => <div className="timeline-item" key={title}><div className="timeline-marker"><Icon name={icon as IconName} /></div><div className="timeline-content"><div><span>{date}</span><h3>{title}</h3></div><Badge tone={index === 5 ? "green" : "blue"}>{index === 5 ? "Reçu" : "Documenté"}</Badge><strong>{ref}</strong><p>{text}</p></div></div>)}</div></div><aside><div className="info-card"><h3>Origine agricole</h3><img src={fieldPhoto} alt="Exploitation agricole vue du ciel" /><strong>Ferme des Trois Chênes</strong><p>Valence, Drôme · Claire Martin</p><Button variant="secondary">Voir l’exploitation</Button></div><div className="info-card"><h3>Transparence des données</h3><div className="progress-row"><span>Traçabilité</span><strong>100%</strong></div><div className="progress"><span style={{ width: "100%" }} /></div><div className="progress-row"><span>Impact environnemental</span><strong>75%</strong></div><div className="progress"><span style={{ width: "75%" }} /></div><p className="notice"><Icon name="warning" /> La consommation d’eau reste à confirmer par un justificatif.</p></div></aside></div></div>;
  if (embedded) return content;
  return <div className="public subpage"><PublicHeader page="trace" go={go} />{content}<Footer /></div>;
}

function Compare({ go }: { go: (page: Page) => void }) {
  const criteria = [["Origine","Drôme, France","Vaucluse, France"],["Producteur","Ferme des Trois Chênes","Vergers du Soleil"],["Traçabilité","6 étapes sur 6","5 étapes sur 6"],["Certification","Bio · vérifiée","Label régional · vérifié"],["Émissions CO₂e","0,42 kg/kg","0,68 kg/L"],["Consommation d’eau","Donnée à confirmer","42 L/L"],["Sources disponibles","5 documents","4 documents"]];
  return <div className="public subpage"><PublicHeader page="compare" go={go} /><main className="public-content"><div className="catalogue-head"><div><span className="eyebrow">Comparaison factuelle</span><h1>Comparez les données disponibles</h1><p>Aucun classement arbitraire : vous gardez le contexte, les sources et les données manquantes.</p></div></div><div className="compare-picker"><div><span>Produit A</span><strong>Tomates anciennes</strong></div><div><span>Produit B</span><strong>Jus de pomme artisanal</strong></div><Button variant="secondary" icon="plus">Ajouter un produit</Button></div><div className="compare-table"><div className="compare-row compare-title"><strong>Critère</strong><strong>Tomates anciennes</strong><strong>Jus de pomme</strong></div>{criteria.map((row) => <div className="compare-row" key={row[0]}><strong>{row[0]}</strong><span>{row[1]}</span><span>{row[2]}</span></div>)}</div><div className="comparison-note"><Icon name="spark" /><div><strong>Résumé NutriTrace AI — démonstration</strong><p>Les deux produits disposent d’une origine et de certifications documentées. Une comparaison environnementale directe reste limitée car les unités fonctionnelles diffèrent.</p></div></div></main><Footer /></div>;
}

function Footer() {
  return <footer><Logo /><p>La transparence à chaque étape de la chaîne alimentaire.</p><span>© 2025 NutriTrace · Prototype universitaire</span></footer>;
}

function Login({ go }: { go: (page: Page) => void }) {
  const [loading, setLoading] = useState(false);
  const submit = () => { setLoading(true); window.setTimeout(() => { setLoading(false); go("dashboard"); }, 650); };
  return <div className="auth-page"><div className="auth-art"><Logo /><div className="auth-message"><Badge tone="green">Plateforme de confiance</Badge><h1>La traçabilité alimentaire, simplement.</h1><p>Accédez aux données de votre organisation et collaborez avec tous les acteurs de la chaîne.</p></div><div className="auth-quote"><p>“NutriTrace nous aide à documenter chaque étape sans perdre le lien avec l’origine.”</p><span>Claire Martin · Productrice</span></div></div><div className="auth-form"><button className="back-link" onClick={() => go("home")}>← Retour au site</button><div className="form-panel"><span className="eyebrow">Bienvenue</span><h2>Connectez-vous à NutriTrace</h2><p>Utilisez vos identifiants professionnels.</p><form onSubmit={(event) => { event.preventDefault(); submit(); }}><Field label="Adresse e-mail" placeholder="prenom@organisation.fr" type="email" /><Field label="Mot de passe" placeholder="••••••••" type="password" /><div className="form-options"><label><input type="checkbox" /> Se souvenir de moi</label><button type="button">Mot de passe oublié ?</button></div><Button type="submit" disabled={loading}>{loading ? "Connexion en cours..." : "Se connecter"}</Button></form><div className="demo-access"><Icon name="check" /><span><strong>Accès démonstration</strong>Les identifiants sont préremplis pour explorer le prototype.</span></div><p className="signup">Nouveau sur NutriTrace ? <button>Créer un compte professionnel</button></p></div></div></div>;
}

function Sidebar({ page, go, open, close }: { page: Page; go: (page: Page) => void; open: boolean; close: () => void }) {
  return <aside className={`sidebar ${open ? "sidebar-open" : ""}`}><div className="sidebar-logo"><Logo /><button className="mobile-close" onClick={close}><Icon name="close" /></button></div><nav>{navGroups.map((group) => <div className="nav-group" key={group.label}><span>{group.label}</span>{group.items.map((item) => <button key={item.page} className={page === item.page ? "active" : ""} onClick={() => { go(item.page); close(); }}><Icon name={item.icon} /><span>{item.label}</span>{item.page === "ai" && <small>8</small>}</button>)}</div>)}</nav><div className="sidebar-user"><div className="avatar">AD</div><div><strong>Anna Dupont</strong><span>Administratrice</span></div><Icon name="more" /></div></aside>;
}

function Topbar({ title, onMenu, go }: { title: string; onMenu: () => void; go: (page: Page) => void }) {
  return <header className="topbar"><div className="topbar-title"><button className="menu-button" onClick={onMenu}><Icon name="menu" /></button><div><span>Administration</span><h1>{title}</h1></div></div><div className="top-actions"><div className="global-search"><Icon name="search" /><input placeholder="Rechercher partout..." /><kbd>⌘ K</kbd></div><button className="icon-btn notification"><Icon name="bell" /><span /></button><button className="avatar-btn" onClick={() => go("home")}>AD</button></div></header>;
}

function Shell({ page, go, children, title }: { page: Page; go: (page: Page) => void; children: ReactNode; title: string }) {
  const [menu, setMenu] = useState(false);
  return <div className="app-shell"><Sidebar page={page} go={go} open={menu} close={() => setMenu(false)} />{menu && <div className="sidebar-scrim" onClick={() => setMenu(false)} />}<div className="app-main"><Topbar title={title} onMenu={() => setMenu(true)} go={go} /><main className="workspace">{children}</main></div></div>;
}

function StatCard({ icon, label, value, change, tone = "green" }: { icon: IconName; label: string; value: string; change: string; tone?: string }) {
  return <div className="stat-card"><div className={`stat-icon tone-${tone}`}><Icon name={icon} /></div><div><span>{label}</span><strong>{value}</strong><small>{change}</small></div></div>;
}

function Dashboard({ go }: { go: (page: Page) => void }) {
  return <Shell page="dashboard" go={go} title="Tableau de bord"><div className="welcome"><div><h2>Bonjour Anna, voici l’essentiel.</h2><p>Suivez les opérations et les éléments qui nécessitent votre attention.</p></div><div><Button variant="secondary" icon="download">Exporter</Button><Button icon="plus" onClick={() => go("products")}>Nouveau produit</Button></div></div>
    <div className="stats-grid"><StatCard icon="box" label="Produits" value="128" change="+12 ce mois" /><StatCard icon="farm" label="Exploitations" value="47" change="+3 ce mois" tone="blue" /><StatCard icon="shuffle" label="Lots actifs" value="236" change="91% reliés" tone="purple" /><StatCard icon="award" label="Certifications en attente" value="12" change="À examiner" tone="orange" /></div>
    <div className="dashboard-grid"><section className="panel chart-panel"><div className="panel-head"><div><h3>Évolution des produits</h3><span>Produits documentés sur les 6 derniers mois</span></div><select><option>6 derniers mois</option><option>Cette année</option></select></div><div className="chart"><div className="chart-y"><span>140</span><span>105</span><span>70</span><span>35</span><span>0</span></div><div className="chart-area"><svg viewBox="0 0 600 210" preserveAspectRatio="none"><defs><linearGradient id="area" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stopColor="#2D6A4F" stopOpacity=".22" /><stop offset="100%" stopColor="#2D6A4F" stopOpacity="0" /></linearGradient></defs><path className="area" d="M0,180 C65,170 90,145 140,150 C200,155 215,110 280,120 C340,130 365,75 420,90 C480,105 520,35 600,42 L600,210 L0,210Z" /><path className="line" d="M0,180 C65,170 90,145 140,150 C200,155 215,110 280,120 C340,130 365,75 420,90 C480,105 520,35 600,42" /></svg><div className="chart-labels"><span>Jan</span><span>Fév</span><span>Mar</span><span>Avr</span><span>Mai</span><span>Juin</span></div></div></div></section><section className="panel alerts-panel"><div className="panel-head"><div><h3>Alertes à examiner</h3><span>Analyses automatiques récentes</span></div><Badge tone="orange">8 alertes</Badge></div>{[["warning","Rendement inhabituel","TRF-2025-084 · Farine T80","Élevée"],["clock","Réception en retard","DIS-2506-101 · Les Halles Vertes","Moyenne"],["award","Certificat bientôt expiré","Label régional · 12 jours","Moyenne"]].map(([icon,title,text,level]) => <button className="alert-row" key={title}><span className={`alert-icon ${level === "Élevée" ? "danger" : ""}`}><Icon name={icon as IconName} /></span><span><strong>{title}</strong><small>{text}</small></span><Icon name="arrow" /></button>)}<Button variant="ghost" onClick={() => go("ai")}>Voir toutes les analyses <Icon name="arrow" /></Button></section></div>
    <div className="management"><div className="panel-head"><div><h3>Accès rapides aux gestions</h3><span>Les quatre domaines métier de NutriTrace</span></div></div><div className="management-grid">{[["farm","Production & produits","47 exploitations · 128 produits","farms"],["shuffle","Transformations & lots","64 transformations · 236 lots","transformations"],["truck","Distribution & points de vente","89 mouvements · 32 points","distributions"],["award","Certifications & impact","84 certificats · 196 mesures","certifications"]].map(([icon,title,text,target]) => <button key={title} onClick={() => go(target as Page)}><span><Icon name={icon as IconName} /></span><div><strong>{title}</strong><small>{text}</small></div><Icon name="arrow" /></button>)}</div></div>
    <div className="lower-grid"><section className="panel"><div className="panel-head"><div><h3>Activités récentes</h3><span>Dernières opérations enregistrées</span></div><Button variant="ghost">Tout afficher</Button></div><div className="activity-list">{[["CM","Claire Martin","a ajouté un produit","Tomates anciennes · il y a 18 min"],["ML","Mehdi Laurent","a terminé une transformation","TRF-2025-089 · il y a 1 h"],["SB","Sophie Bernard","a confirmé une réception","DIS-2506-104 · il y a 2 h"],["AD","Anna Dupont","a validé une certification","Agriculture Biologique · il y a 3 h"]].map((a) => <div className="activity" key={a[3]}><span>{a[0]}</span><div><p><strong>{a[1]}</strong> {a[2]}</p><small>{a[3]}</small></div></div>)}</div></section><section className="panel impact-panel"><div className="panel-head"><div><h3>Complétude environnementale</h3><span>Sur l’ensemble des produits publiés</span></div></div><div className="donut"><div><strong>68%</strong><span>documenté</span></div></div><div className="legend"><span><i className="green" /> Vérifié <strong>68%</strong></span><span><i className="light" /> À vérifier <strong>19%</strong></span><span><i className="gray" /> Manquant <strong>13%</strong></span></div></section></div>
  </Shell>;
}

function EntityPage({ page, go }: { page: Page; go: (page: Page) => void }) {
  const config = entityConfigs[page]!;
  const [modal, setModal] = useState<"create" | "detail" | "delete" | null>(null);
  const [query, setQuery] = useState("");
  const rows = useMemo(() => config.rows.filter((row) => row.some((cell) => cell.toLowerCase().includes(query.toLowerCase()))), [config.rows, query]);
  return <Shell page={page} go={go} title={config.title}><div className="page-heading"><div><div className="breadcrumbs">Tableau de bord <Icon name="arrow" size={14} /> {config.title}</div><h2>{config.title}</h2><p>{config.subtitle}</p></div><div><Button variant="secondary" icon="download">Exporter</Button><Button icon="plus" onClick={() => setModal("create")}>Ajouter {config.singular === "indicateur" ? "un" : "une"} {config.singular}</Button></div></div>
    <div className="mini-stats">{config.stats.map(([value,label], index) => <div key={label}><span className={`mini-stat-icon tone-${index === 2 ? "orange" : index === 1 ? "blue" : "green"}`}><Icon name={index === 2 ? "warning" : config.icon} /></span><div><strong>{value}</strong><span>{label}</span></div></div>)}</div>
    <section className="table-panel"><div className="table-tools"><div className="search-control"><Icon name="search" /><input value={query} onChange={(event) => setQuery(event.target.value)} placeholder={`Rechercher ${config.singular}...`} /></div><Button variant="secondary" icon="filter">Filtres</Button><select><option>Tous les statuts</option><option>Actif</option><option>À vérifier</option></select></div><div className="data-table"><div className="table-row table-header">{config.columns.map((column) => <span key={column}>{column}</span>)}<span>Actions</span></div>{rows.map((row, index) => <div className="table-row" key={row[0]}>{row.map((cell, cellIndex) => <span key={cellIndex}>{cellIndex === 0 ? <strong>{cell}</strong> : cellIndex === row.length - 1 ? <Badge tone={cell.includes("retard") || cell.includes("expir") || cell.includes("Incomplet") ? "red" : cell.includes("attente") || cell.includes("vérifier") || cell.includes("renouveler") || cell.includes("Brouillon") ? "orange" : "green"}>{cell}</Badge> : cell}</span>)}<span className="row-actions"><button onClick={() => setModal("detail")} title="Consulter"><Icon name="eye" /></button><button onClick={() => setModal("create")} title="Modifier"><Icon name="edit" /></button><button onClick={() => setModal("delete")} title="Archiver"><Icon name="trash" /></button></span></div>)}{rows.length === 0 && <div className="empty-state"><Icon name="search" size={28} /><strong>Aucun résultat</strong><span>Essayez une autre recherche ou réinitialisez les filtres.</span></div>}</div><div className="pagination"><span>{rows.length} résultat(s) sur {config.rows.length}</span><div><Button variant="secondary" disabled>Précédent</Button><button className="page-number active">1</button><button className="page-number">2</button><Button variant="secondary">Suivant</Button></div></div></section>
    {modal === "create" && <EntityForm config={config} onClose={() => setModal(null)} />}
    {modal === "detail" && <EntityDetail config={config} onClose={() => setModal(null)} />}
    {modal === "delete" && <Modal title={`Archiver cette ${config.singular}`} onClose={() => setModal(null)}><div className="confirm-body"><span className="confirm-icon"><Icon name="warning" /></span><p>Cette action retirera l’élément des listes actives. Son historique et ses relations seront conservés.</p><div className="modal-actions"><Button variant="secondary" onClick={() => setModal(null)}>Annuler</Button><Button variant="danger" onClick={() => setModal(null)}>Confirmer l’archivage</Button></div></div></Modal>}
  </Shell>;
}

function EntityForm({ config, onClose }: { config: NonNullable<(typeof entityConfigs)[Page]>; onClose: () => void }) {
  const fields = config.singular === "produit" ? [["Nom du produit","Tomates anciennes"],["Référence","PRD-2025-042"],["Catégorie","Sélectionner une catégorie"],["Exploitation associée","Sélectionner une exploitation"],["Origine","Drôme, France"],["Quantité produite","1 200 kg"]] : config.singular === "certification" ? [["Type de certification","Agriculture Biologique"],["Produit concerné","Sélectionner un produit"],["Organisme certificateur","Nom de l’organisme"],["N° du certificat","CERT-2025-"],["Date de délivrance","jj/mm/aaaa"],["Date d’expiration","jj/mm/aaaa"]] : [["Nom / Référence",`Nouvelle ${config.singular}`],["Statut","Sélectionner un statut"],[config.relation,"Sélectionner dans la liste"],["Responsable","Sélectionner un utilisateur"],["Date","jj/mm/aaaa"],["Justificatif","Ajouter un document"]];
  return <Modal title={`Ajouter ${config.singular === "indicateur" ? "un" : "une"} ${config.singular}`} onClose={onClose} wide><form onSubmit={(event) => { event.preventDefault(); onClose(); }}><div className="form-section"><div className="form-section-title"><span>01</span><div><h3>Informations principales</h3><p>Renseignez les données nécessaires à l’identification.</p></div></div><div className="form-grid">{fields.map(([label,placeholder], index) => <Field key={label} label={`${label}${index < 4 ? " *" : ""}`} placeholder={placeholder} options={label.includes("Statut") || label.includes("associée") || label.includes("concerné") || label === config.relation || label === "Responsable" || label === "Catégorie" ? ["Option principale","Option secondaire","À vérifier"] : undefined} />)}</div><label className="field full"><span>Description et notes</span><textarea placeholder="Ajoutez les informations utiles..." /></label></div><div className="relation-callout"><Icon name={config.icon} /><div><strong>Relation : {config.relation}</strong><p>La sélection permet de préserver la continuité de la traçabilité et sera visible sur la fiche détail.</p></div></div><div className="modal-actions"><Button variant="secondary" onClick={onClose}>Annuler</Button><Button variant="secondary">Enregistrer en brouillon</Button><Button type="submit">Enregistrer</Button></div></form></Modal>;
}

function EntityDetail({ config, onClose }: { config: NonNullable<(typeof entityConfigs)[Page]>; onClose: () => void }) {
  return <Modal title={`Détail — ${config.rows[0][0]}`} onClose={onClose} wide><div className="detail-hero"><div className={`detail-icon tone-green`}><Icon name={config.icon} size={26} /></div><div><Badge>Actif et documenté</Badge><h3>{config.rows[0][0]}</h3><p>Dernière mise à jour aujourd’hui à 10:42 par Anna Dupont</p></div><Button variant="secondary" icon="edit">Modifier</Button></div><div className="detail-grid"><section><h3>Informations générales</h3>{config.columns.slice(1).map((column, index) => <div className="detail-row" key={column}><span>{column}</span><strong>{config.rows[0][index + 1]}</strong></div>)}</section><section><h3>{config.relation}</h3><div className="linked-record"><span className="detail-icon tone-blue"><Icon name={config.icon} /></span><div><strong>{config.rows[1]?.[0] || "Élément associé"}</strong><span>Relation active · Données synchronisées</span></div><Icon name="arrow" /></div><h3 className="history-title">Historique récent</h3><div className="history"><p><i />Données mises à jour <span>Aujourd’hui, 10:42</span></p><p><i />Justificatif ajouté <span>Hier, 16:08</span></p><p><i />Création de la fiche <span>12 juin 2025</span></p></div></section></div><div className="modal-actions"><Button variant="secondary" onClick={onClose}>Fermer</Button><Button icon="download">Exporter la fiche</Button></div></Modal>;
}

function AICenter({ go }: { go: (page: Page) => void }) {
  const [active, setActive] = useState("Analyse globale");
  const [asked, setAsked] = useState(false);
  return <Shell page="ai" go={go} title="NutriTrace AI"><div className="ai-page"><aside className="ai-history"><Button icon="plus">Nouvelle analyse</Button><span className="history-label">Conversations récentes</span>{["Analyse globale","Parcours du lot TOM-2506","Certificat BIO-2048","Comparaison environnementale"].map((item) => <button className={active === item ? "active" : ""} onClick={() => setActive(item)} key={item}><Icon name="spark" /><span>{item}<small>Il y a 2 h</small></span></button>)}<div className="ai-disclaimer"><Icon name="warning" /><p><strong>Assistant de démonstration</strong>Les réponses illustrent les possibilités de l’outil et ne constituent pas une vérification officielle.</p></div></aside><section className="ai-chat"><div className="ai-chat-head"><div className="ai-orb small"><Icon name="spark" /></div><div><h2>{active}</h2><span><i /> NutriTrace AI · Données au 20 juin 2025</span></div><Button variant="secondary" icon="download">Exporter</Button></div><div className="chat-body"><div className="ai-intro"><div className="ai-orb"><Icon name="spark" size={28} /></div><h2>Comment puis-je vous aider ?</h2><p>J’analyse uniquement les données disponibles dans NutriTrace et j’indique leurs limites.</p><div className="suggestions">{["Quelles informations manquent pour ce lot ?","Analyse les transformations inhabituelles","Quels certificats expirent bientôt ?","Compare deux produits documentés"].map((question) => <button onClick={() => setAsked(true)} key={question}><Icon name="spark" />{question}<Icon name="arrow" /></button>)}</div></div>{asked && <div className="answer-block"><div className="user-message">Quelles informations manquent pour ce lot ?</div><div className="ai-answer"><div className="answer-title"><span className="ai-orb small"><Icon name="spark" /></span><div><strong>Analyse du lot LOT-BLE-2505-B</strong><span>Réponse de démonstration · Confiance moyenne</span></div></div><p>Deux éléments nécessitent une vérification humaine avant que la traçabilité puisse être considérée comme complète.</p><div className="finding danger"><Icon name="warning" /><div><strong>Justificatif de transformation manquant</strong><p>La transformation TRF-2025-084 ne contient aucun document lié au contrôle de rendement.</p><span>Données utilisées : quantité entrante 900 kg · sortante 680 kg</span></div><Button variant="secondary">Consulter</Button></div><div className="finding warning"><Icon name="clock" /><div><strong>Méthode de calcul non précisée</strong><p>L’indicateur énergétique ne documente pas sa méthode de calcul.</p><span>Donnée concernée : 0,18 kWh/kg</span></div><Button variant="secondary">Consulter</Button></div><div className="sources"><strong>Sources consultées</strong><span>Fiche lot · Transformation · Historique · Indicateurs environnementaux</span></div></div></div>}</div><form className="chat-input" onSubmit={(event) => { event.preventDefault(); setAsked(true); }}><Icon name="spark" /><input placeholder="Posez une question sur vos données..." /><Button type="submit">Envoyer</Button><small>NutriTrace AI peut se tromper. Vérifiez les informations importantes.</small></form></section></div></Shell>;
}

export default function App() {
  const [page, setPage] = useState<Page>("home");
  const go = (next: Page) => { setPage(next); window.scrollTo({ top: 0, behavior: "smooth" }); };
  if (page === "home") return <Home go={go} />;
  if (page === "catalogue") return <Catalogue go={go} />;
  if (page === "compare") return <Compare go={go} />;
  if (page === "trace") return <Traceability go={go} />;
  if (page === "login") return <Login go={go} />;
  if (page === "dashboard") return <Dashboard go={go} />;
  if (page === "ai") return <AICenter go={go} />;
  return <EntityPage page={page} go={go} />;
}

import { useMemo, useState } from 'react'
import {
  Activity, AlertTriangle, ArrowLeft, ArrowRight, BarChart3, Bell, Bot, Box, Camera,
  Check, CheckCircle2, ChevronDown, ChevronRight, CircleHelp, ClipboardCheck,
  Clock3, Database, Eye, Factory, FileCheck2, FileText, Filter, Heart,
  History, Home, KeyRound, Leaf, LockKeyhole, LogOut, MapPin, Menu, MessageSquareText, MoreHorizontal,
  Package, Pencil, Plus, QrCode, ScanFace, Search, Settings, ShieldCheck, SlidersHorizontal,
  Sparkles, Store, Tractor, TrendingUp, Truck, Upload, User, UserRound,
  Users, Wheat, X, XCircle, type LucideIcon
} from 'lucide-react'

type Role = 'Administrateur' | 'Producteur' | 'Transformateur' | 'Distributeur' | 'Consommateur'
type Mode = 'app' | 'login' | 'register' | 'public' | 'design'

const roleConfig: Record<Role, { icon: LucideIcon; name: string; org: string; menu: string[] }> = {
  Administrateur: { icon: ShieldCheck, name: 'Claire Martin', org: 'Administration NutriTrace', menu: ['Tableau de bord', 'Utilisateurs et rôles', 'Producteurs', 'Exploitations', 'Produits', 'Transformations', 'Lots', 'Distributions', 'Certifications', 'Indicateurs environnementaux', 'Analyses et alertes', 'Journal d’audit', 'Paramètres'] },
  Producteur: { icon: Tractor, name: 'Julien Moreau', org: 'Ferme des Trois Chênes', menu: ['Tableau de bord', 'Mes exploitations', 'Mes produits', 'Mes lots', 'Mes demandes de certification', 'Mes justificatifs', 'Assistant IA', 'Notifications', 'Mon profil'] },
  Transformateur: { icon: Factory, name: 'Sophie Bernard', org: 'Atelier Saveurs & Sens', menu: ['Tableau de bord', 'Produits accessibles', 'Transformations', 'Lots entrants', 'Lots sortants', 'Historique', 'Alertes de cohérence', 'Assistant IA', 'Notifications', 'Mon profil'] },
  Distributeur: { icon: Truck, name: 'Marc Dubois', org: 'Circuit Court Ouest', menu: ['Tableau de bord', 'Lots disponibles', 'Distributions', 'Nouvelle distribution', 'Historique des mouvements', 'Alertes', 'Assistant IA', 'Notifications', 'Mon profil'] },
  Consommateur: { icon: UserRound, name: 'Élodie Petit', org: 'Compte particulier', menu: ['Tableau de bord', 'Explorer les produits', 'Traçabilité', 'Comparateur', 'Mes favoris', 'Assistant IA', 'Mon profil'] },
}

const iconFor = (label: string): LucideIcon => {
  const l = label.toLowerCase()
  if (l.includes('tableau')) return Home
  if (l.includes('utilisateur') || l.includes('producteur')) return Users
  if (l.includes('exploitation')) return Tractor
  if (l.includes('produit')) return Wheat
  if (l.includes('transformation')) return Factory
  if (l.includes('lot')) return Package
  if (l.includes('distribution') || l.includes('mouvement')) return Truck
  if (l.includes('certification')) return FileCheck2
  if (l.includes('ia') || l.includes('analyse')) return Bot
  if (l.includes('alerte')) return AlertTriangle
  if (l.includes('historique') || l.includes('audit')) return History
  if (l.includes('notification')) return Bell
  if (l.includes('favoris')) return Heart
  if (l.includes('comparateur')) return SlidersHorizontal
  if (l.includes('traçabilité')) return QrCode
  if (l.includes('paramètre')) return Settings
  if (l.includes('profil')) return User
  return FileText
}

const entities: Record<string, { singular: string; columns: string[]; rows: string[][] }> = {
  Produits: { singular: 'produit', columns: ['Produit', 'Référence', 'Catégorie', 'Origine', 'Statut'], rows: [['Tomates anciennes', 'PRD-2025-0842', 'Légumes', 'Loire-Atlantique', 'Complet'], ['Farine de blé T80', 'PRD-2025-0774', 'Céréales', 'Maine-et-Loire', 'À compléter'], ['Jus de pomme bio', 'PRD-2025-0691', 'Boissons', 'Vendée', 'Certifié'], ['Lentilles vertes', 'PRD-2025-0618', 'Légumineuses', 'Indre', 'Complet']] },
  Lots: { singular: 'lot', columns: ['Référence', 'Produit', 'Quantité', 'Création', 'Statut'], rows: [['LOT-2506-118', 'Tomates anciennes', '480 kg', '18 juin 2025', 'Disponible'], ['LOT-2506-104', 'Farine de blé T80', '1 250 kg', '16 juin 2025', 'En transit'], ['LOT-2506-096', 'Jus de pomme bio', '680 L', '14 juin 2025', 'Distribué'], ['LOT-2506-081', 'Lentilles vertes', '320 kg', '11 juin 2025', 'Disponible']] },
  Transformations: { singular: 'transformation', columns: ['Référence', 'Produit / lot', 'Opération', 'Date', 'Statut'], rows: [['TRF-2025-312', 'LOT-2506-104', 'Mouture sur meule', '18 juin 2025', 'Validée'], ['TRF-2025-308', 'LOT-2506-096', 'Pressage à froid', '15 juin 2025', 'Validée'], ['TRF-2025-301', 'PRD-2025-0842', 'Tri et calibrage', '12 juin 2025', 'Brouillon'], ['TRF-2025-294', 'LOT-2506-081', 'Nettoyage', '10 juin 2025', 'À vérifier']] },
  Certifications: { singular: 'demande', columns: ['Dossier', 'Produit', 'Certification', 'Demandeur', 'Statut'], rows: [['CERT-2025-089', 'Tomates anciennes', 'Agriculture biologique', 'Julien Moreau', 'En attente'], ['CERT-2025-082', 'Jus de pomme bio', 'Label NutriTrace', 'Sophie Bernard', 'Approuvée'], ['CERT-2025-074', 'Farine de blé T80', 'HVE', 'Julien Moreau', 'À compléter'], ['CERT-2025-068', 'Lentilles vertes', 'Agriculture biologique', 'Luc Garnier', 'Expirée']] },
  Distributions: { singular: 'distribution', columns: ['Mouvement', 'Lot', 'Origine', 'Destination', 'Statut'], rows: [['MVT-2025-441', 'LOT-2506-118', 'Nantes', 'Rennes', 'En cours'], ['MVT-2025-436', 'LOT-2506-104', 'Angers', 'Nantes', 'Livrée'], ['MVT-2025-421', 'LOT-2506-096', 'La Roche-sur-Yon', 'Tours', 'Livrée'], ['MVT-2025-417', 'LOT-2506-081', 'Châteauroux', 'Paris', 'Planifiée']] },
}

const photo = 'https://images.unsplash.com/photo-1560493676-04071c5f467b?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&q=80&w=1400'

function Logo({ light = false }: { light?: boolean }) {
  return <div className={`logo ${light ? 'logo-light' : ''}`}><span className="logo-mark"><Leaf size={21} strokeWidth={2.4}/><span /></span><strong>NutriTrace</strong></div>
}

function Button({ children, variant = 'primary', icon: Icon, onClick, type = 'button', disabled = false }: { children: React.ReactNode; variant?: 'primary'|'secondary'|'ghost'|'danger'; icon?: LucideIcon; onClick?: () => void; type?: 'button'|'submit'; disabled?: boolean }) {
  return <button type={type} disabled={disabled} onClick={onClick} className={`btn btn-${variant}`}>{Icon && <Icon size={17}/>}<span>{children}</span></button>
}

function Field({ label, placeholder, type = 'text', error }: { label: string; placeholder?: string; type?: string; error?: string }) {
  return <label className="field"><span>{label}</span><input type={type} placeholder={placeholder}/>{error && <small><AlertTriangle size={13}/>{error}</small>}</label>
}

function Badge({ children, tone = 'green' }: { children: React.ReactNode; tone?: 'green'|'amber'|'red'|'blue'|'gray' }) {
  return <span className={`badge badge-${tone}`}><span className="badge-dot"/>{children}</span>
}

function AuthScreen({ register, onMode, onEnter }: { register?: boolean; onMode: (m: Mode) => void; onEnter: (r: Role) => void }) {
  const [role, setRole] = useState<Role>('Producteur')
  const roles: Role[] = ['Producteur', 'Transformateur', 'Distributeur', 'Consommateur']
  return <div className="auth-shell">
    <section className="auth-story" style={{ backgroundImage: `linear-gradient(180deg, rgba(15,51,38,.24), rgba(15,51,38,.92)),url("${photo}")` }}>
      <Logo light/>
      <div className="auth-copy"><Badge>Plateforme de confiance</Badge><h1>{register ? 'Rejoignez une chaîne alimentaire plus transparente.' : 'Bon retour parmi nous.'}</h1><p>De la ferme à votre assiette, la transparence à chaque étape.</p><div className="story-proof"><ShieldCheck/><span><strong>Données vérifiables</strong><small>Une traçabilité claire et partagée</small></span></div></div>
      <small>Photo : Dan Meyers · Unsplash</small>
    </section>
    <main className="auth-main">
      <button className="back-link" onClick={() => onMode('public')}><ArrowLeft size={16}/> Retour au site</button>
      <div className={`auth-card ${register ? 'wide' : ''}`}>
        <div><span className="eyebrow">{register ? 'Créer votre espace' : 'Accès sécurisé'}</span><h2>{register ? 'Créer un compte' : 'Connexion'}</h2><p>{register ? 'Sélectionnez votre activité pour personnaliser votre expérience.' : 'Accédez à votre espace NutriTrace.'}</p></div>
        {register && <div className="role-cards">{roles.map(r => { const I = roleConfig[r].icon; return <button key={r} className={role === r ? 'selected' : ''} onClick={() => setRole(r)}><I size={20}/><span><strong>{r}</strong><small>{r === 'Producteur' ? 'Exploitations et productions' : r === 'Transformateur' ? 'Opérations de transformation' : r === 'Distributeur' ? 'Lots et mouvements' : 'Explorer les produits'}</small></span>{role === r && <Check size={16}/>}</button>})}</div>}
        <form onSubmit={e => { e.preventDefault(); onEnter(role) }} className="auth-form">
          {register && <div className="form-row"><Field label="Nom complet" placeholder="Votre nom"/><Field label="Téléphone (facultatif)" placeholder="+33 6 00 00 00 00"/></div>}
          <Field label="Adresse e-mail" placeholder="vous@exemple.fr" type="email"/>
          <Field label="Mot de passe" placeholder="8 caractères minimum" type="password"/>
          {register && <><div className="strength"><span/><span/><span/><i/> <small>Robuste</small></div><Field label="Confirmer le mot de passe" placeholder="Saisissez-le à nouveau" type="password"/></>}
          <div className="form-meta"><label><input type="checkbox" defaultChecked/> {register ? 'J’accepte les conditions d’utilisation' : 'Se souvenir de moi'}</label><button type="button">Mot de passe oublié ?</button></div>
          <Button type="submit" icon={ArrowRight}>{register ? 'Créer mon compte' : 'Se connecter'}</Button>
        </form>
        <p className="auth-switch">{register ? 'Déjà inscrit ?' : 'Nouveau sur NutriTrace ?'} <button onClick={() => onMode(register ? 'login' : 'register')}>{register ? 'Se connecter' : 'Créer un compte'}</button></p>
        {register && <div className="admin-note"><ShieldCheck size={17}/><span>Les comptes Administrateur sont attribués uniquement par NutriTrace.</span></div>}
      </div>
    </main>
  </div>
}

function PublicHome({ onMode }: { onMode: (m: Mode) => void }) {
  return <div className="public-shell">
    <header className="public-nav"><Logo/><nav><button>Accueil</button><button>Produits</button><button>Traçabilité</button><button>À propos</button><button>Contact</button></nav><div><Button variant="ghost" onClick={() => onMode('login')}>Connexion</Button><Button onClick={() => onMode('register')}>Créer un compte</Button></div></header>
    <main>
      <section className="hero"><div className="hero-copy"><Badge>La traçabilité qui rassemble</Badge><h1>Comprenez vraiment ce que vous consommez.</h1><p>NutriTrace relie producteurs, transformateurs, distributeurs et consommateurs autour d’informations alimentaires fiables.</p><div className="hero-actions"><Button icon={Search} onClick={() => onMode('login')}>Explorer les produits</Button><Button variant="secondary" icon={QrCode}>Tracer un lot</Button></div><div className="trust-row"><span><CheckCircle2/>Données sourcées</span><span><CheckCircle2/>Parcours lisible</span><span><CheckCircle2/>Acteurs vérifiés</span></div></div><div className="hero-visual"><img src={photo} alt="Rangs de cultures durables au coucher du soleil"/><div className="trace-card"><div><span className="icon-tile"><Wheat/></span><span><small>LOT-2506-118</small><strong>Tomates anciennes</strong></span></div><div className="mini-timeline"><i/><i/><i/><i/><span>4 étapes vérifiées</span></div></div></div></section>
      <section className="public-stats"><div><strong>12 840</strong><span>produits tracés</span></div><div><strong>1 260</strong><span>acteurs engagés</span></div><div><strong>94%</strong><span>de parcours complets</span></div><div><strong>24/7</strong><span>accès aux données</span></div></section>
    </main>
  </div>
}

function Sidebar({ role, active, setActive, open, close, onMode }: { role: Role; active: string; setActive: (s: string) => void; open: boolean; close: () => void; onMode: (m: Mode) => void }) {
  const cfg = roleConfig[role]
  return <><aside className={`sidebar ${open ? 'sidebar-open' : ''}`}><div className="side-brand"><Logo light/><button onClick={close}><X/></button></div><div className="workspace-label">ESPACE {role.toUpperCase()}</div><nav>{cfg.menu.map(item => { const Icon = iconFor(item); return <button key={item} className={active === item ? 'active' : ''} onClick={() => { setActive(item); close() }}><Icon size={18}/><span>{item}</span>{item === 'Notifications' && <em>3</em>}</button>})}</nav><div className="side-bottom"><button onClick={() => onMode('public')}><Eye size={18}/>Voir le site public</button><button onClick={() => onMode('login')}><LogOut size={18}/>Déconnexion</button></div></aside>{open && <div className="scrim" onClick={close}/>}</>
}

function Topbar({ role, onMenu, onRole }: { role: Role; onMenu: () => void; onRole: (r: Role) => void }) {
  const cfg = roleConfig[role]
  return <header className="topbar"><button className="mobile-menu" onClick={onMenu}><Menu/></button><div className="global-search"><Search size={18}/><input placeholder="Rechercher un produit, un lot, une référence…"/><kbd>⌘ K</kbd></div><div className="top-actions"><button className="help"><CircleHelp/></button><button className="notification"><Bell/><i/></button><label className="role-switch"><span className="avatar">{cfg.name.split(' ').map(x => x[0]).join('')}</span><span><strong>{cfg.name}</strong><small>{role}</small></span><ChevronDown size={16}/><select value={role} onChange={e => onRole(e.target.value as Role)}>{Object.keys(roleConfig).map(r => <option key={r}>{r}</option>)}</select></label></div></header>
}

function StatCard({ icon: Icon, label, value, delta, tone = 'green' }: { icon: LucideIcon; label: string; value: string; delta?: string; tone?: string }) {
  return <article className="stat-card"><div className={`stat-icon tone-${tone}`}><Icon/></div><div><span>{label}</span><strong>{value}</strong>{delta && <small><TrendingUp/> {delta}</small>}</div><button><MoreHorizontal/></button></article>
}

const dashboardData: Record<Role, [string,string,LucideIcon][]> = {
  Administrateur: [['Produits', '12 840', Wheat], ['Producteurs', '486', Users], ['Lots actifs', '2 108', Package], ['Certifications en attente', '28', FileCheck2]],
  Producteur: [['Mes exploitations', '3', Tractor], ['Mes produits', '24', Wheat], ['Mes lots actifs', '18', Package], ['Demandes en attente', '4', FileCheck2]],
  Transformateur: [['Produits accessibles', '142', Wheat], ['Transformations', '68', Factory], ['Lots concernés', '36', Package], ['Alertes à traiter', '5', AlertTriangle]],
  Distributeur: [['Lots accessibles', '94', Package], ['Distributions', '128', Truck], ['En cours', '12', Clock3], ['Alertes quantité', '3', AlertTriangle]],
  Consommateur: [['Produits consultés', '28', Eye], ['Favoris', '8', Heart], ['Parcours complets', '21', CheckCircle2], ['Comparaisons', '4', SlidersHorizontal]],
}

function Dashboard({ role, navigate }: { role: Role; navigate: (s: string) => void }) {
  const stats = dashboardData[role]
  if (role === 'Consommateur') return <ConsumerDashboard navigate={navigate}/>
  return <div className="page-stack">
    <div className="welcome"><div><span className="eyebrow">VUE D’ENSEMBLE · 20 JUIN 2025</span><h1>Bonjour {roleConfig[role].name.split(' ')[0]},</h1><p>{role === 'Administrateur' ? 'Voici ce qui se passe aujourd’hui sur la plateforme.' : 'Suivez votre activité et complétez vos données de traçabilité.'}</p></div><Button icon={Plus} onClick={() => navigate(role === 'Transformateur' ? 'Transformations' : role === 'Distributeur' ? 'Nouvelle distribution' : 'Produits')}>{role === 'Producteur' ? 'Ajouter un produit' : role === 'Transformateur' ? 'Nouvelle transformation' : role === 'Distributeur' ? 'Nouvelle distribution' : 'Ajouter un enregistrement'}</Button></div>
    <section className="stats-grid">{stats.map(([label,value,icon], i) => <StatCard key={label} label={label} value={value} icon={icon} delta={i < 2 ? `+${8-i*3}% ce mois` : undefined} tone={i === 3 ? 'amber' : i === 1 ? 'blue' : 'green'}/>)}</section>
    <section className="dashboard-grid">
      <article className="panel chart-panel"><div className="panel-head"><div><h2>Activité de traçabilité</h2><p>Enregistrements sur les 6 derniers mois</p></div><button className="select-button">6 derniers mois <ChevronDown/></button></div><div className="chart"><div className="chart-labels"><span>300</span><span>200</span><span>100</span><span>0</span></div><div className="bars">{[38,52,48,66,60,84,78,92,70,95,88,100].map((h,i) => <i key={i} style={{ height: `${h}%` }}/>)}</div><div className="chart-months"><span>Jan</span><span>Fév</span><span>Mar</span><span>Avr</span><span>Mai</span><span>Juin</span></div></div></article>
      <article className="panel completion-panel"><div className="panel-head"><div><h2>Qualité des données</h2><p>Niveau de complétude</p></div></div><div className="donut"><div><strong>87%</strong><span>complet</span></div></div><ul><li><i className="green"/>Informations vérifiées <strong>1 024</strong></li><li><i className="amber"/>À compléter <strong>126</strong></li><li><i className="red"/>Anomalies <strong>34</strong></li></ul></article>
    </section>
    <section className="dashboard-grid lower"><RecentTable navigate={navigate}/><Alerts navigate={navigate}/></section>
  </div>
}

function RecentTable({ navigate }: { navigate: (s: string) => void }) {
  return <article className="panel recent-panel"><div className="panel-head"><div><h2>Activité récente</h2><p>Dernières mises à jour de la chaîne</p></div><button onClick={() => navigate('Journal d’audit')}>Tout voir <ArrowRight/></button></div><div className="activity-list">{[
    ['LOT-2506-118', 'Tomates anciennes', 'Lot créé par Ferme des Trois Chênes', 'Il y a 12 min', Package],
    ['CERT-2025-089', 'Certification biologique', 'Nouveau justificatif ajouté', 'Il y a 38 min', FileCheck2],
    ['MVT-2025-441', 'Distribution vers Rennes', 'Mouvement en cours', 'Il y a 1 h', Truck],
    ['TRF-2025-312', 'Mouture sur meule', 'Transformation validée', 'Il y a 3 h', Factory],
  ].map(([ref,title,desc,time,icon]) => { const I=icon as LucideIcon; return <button key={ref as string} onClick={() => navigate('Lots')}><span className="activity-icon"><I/></span><span><strong>{title as string}</strong><small>{desc as string}</small></span><span><small>{time as string}</small><code>{ref as string}</code></span><ChevronRight/></button>})}</div></article>
}

function Alerts({ navigate }: { navigate: (s: string) => void }) {
  return <article className="panel alerts-panel"><div className="panel-head"><div><h2>Alertes à traiter</h2><p>3 éléments demandent votre attention</p></div><button onClick={() => navigate('Analyses et alertes')}>Tout voir <ArrowRight/></button></div>{[
    ['Quantité à vérifier', 'LOT-2506-104 · écart de 24 kg', 'amber'],
    ['Étape manquante', 'PRD-2025-0774 · origine incomplète', 'red'],
    ['Certificat expirant', 'CERT-2024-391 · dans 12 jours', 'blue'],
  ].map(([t,d,c]) => <button className="alert-row" key={t} onClick={() => navigate('Analyses et alertes')}><span className={`alert-icon ${c}`}><AlertTriangle/></span><span><strong>{t}</strong><small>{d}</small></span><ChevronRight/></button>)}</article>
}

function ConsumerDashboard({ navigate }: { navigate: (s:string) => void }) {
  return <div className="consumer-home"><div className="consumer-hero"><span className="eyebrow">VOTRE ALIMENTATION, EN TOUTE CLARTÉ</span><h1>Découvrez ce que vous consommez.</h1><p>Recherchez un produit, scannez une référence et parcourez son histoire.</p><div className="big-search"><Search/><input placeholder="Nom, producteur ou référence de lot…"/><Button onClick={() => navigate('Explorer les produits')}>Rechercher</Button></div><button className="scan-link" onClick={() => navigate('Traçabilité')}><QrCode/> J’ai une référence de lot</button></div><div className="section-heading"><div><h2>Vos produits récemment consultés</h2><p>Reprenez là où vous vous êtes arrêté.</p></div><button onClick={() => navigate('Explorer les produits')}>Voir tout <ArrowRight/></button></div><div className="product-grid">{['Tomates anciennes','Jus de pomme bio','Farine de blé T80'].map((p,i)=><article className="product-card" key={p}><div className={`product-image product-${i}`}><Wheat/><button><Heart/></button></div><div><Badge>{i === 1 ? 'Certifié' : 'Traçabilité complète'}</Badge><h3>{p}</h3><p><MapPin/> {i === 0 ? 'Loire-Atlantique' : i === 1 ? 'Vendée' : 'Maine-et-Loire'}</p><button onClick={() => navigate('Traçabilité')}>Voir la traçabilité <ArrowRight/></button></div></article>)}</div></div>
}

function EntityPage({ title, onToast }: { title: string; onToast: (s:string) => void }) {
  const [view, setView] = useState<'list'|'form'|'detail'>('list')
  const [filter, setFilter] = useState('')
  const baseKey = Object.keys(entities).find(k => title.includes(k)) || (title.includes('lot') || title.includes('Lot') ? 'Lots' : title.includes('transformation') || title.includes('Transformation') ? 'Transformations' : title.includes('certification') || title.includes('Certification') ? 'Certifications' : title.includes('distribution') || title.includes('Distribution') ? 'Distributions' : 'Produits')
  const data = entities[baseKey]
  if (view === 'form') return <EntityForm title={title} singular={data.singular} onBack={() => setView('list')} onSave={() => { onToast('Enregistrement sauvegardé avec succès'); setView('detail') }}/>
  if (view === 'detail') return <DetailPage type={baseKey} onBack={() => setView('list')} onEdit={() => setView('form')}/>
  const rows = data.rows.filter(r => r.join(' ').toLowerCase().includes(filter.toLowerCase()))
  return <div className="page-stack"><div className="page-heading"><div><div className="breadcrumbs">Accueil <ChevronRight/> {title}</div><h1>{title}</h1><p>Gérez et suivez les informations de traçabilité associées.</p></div><Button icon={Plus} onClick={() => setView('form')}>Ajouter {data.singular === 'demande' ? 'une' : 'un'} {data.singular}</Button></div>
    {baseKey === 'Certifications' && <div className="tabs">{['En attente  8','Approuvées  46','Refusées  3','Expirées  12','Révoquées  1'].map((t,i)=><button className={i===0?'active':''} key={t}>{t}</button>)}</div>}
    <article className="panel table-panel"><div className="table-tools"><div className="table-search"><Search/><input value={filter} onChange={e=>setFilter(e.target.value)} placeholder={`Rechercher dans ${title.toLowerCase()}…`}/></div><Button variant="secondary" icon={Filter}>Filtres <span className="filter-count">2</span></Button><Button variant="ghost" icon={Upload}>Exporter</Button></div><div className="active-filters"><span>Statut : Actif <button>×</button></span><span>Période : 30 jours <button>×</button></span><button>Effacer les filtres</button></div><div className="data-table"><table><thead><tr><th><input type="checkbox"/></th>{data.columns.map(c=><th key={c}>{c}</th>)}<th>Actions</th></tr></thead><tbody>{rows.map((row,i)=><tr key={i} onClick={()=>setView('detail')}><td><input type="checkbox" onClick={e=>e.stopPropagation()}/></td>{row.map((cell,j)=><td key={j}>{j===0?<strong>{cell}</strong>:j===row.length-1?<Badge tone={cell.includes('attente')||cell.includes('compléter')||cell.includes('cours')||cell.includes('vérifier')?'amber':cell.includes('Expir')?'red':'green'}>{cell}</Badge>:cell}</td>)}<td><button><MoreHorizontal/></button></td></tr>)}</tbody></table></div><div className="pagination"><span>Affichage de 1 à {rows.length} sur 48 résultats</span><div><button disabled><ArrowLeft/></button><button className="active">1</button><button>2</button><button>3</button><button><ArrowRight/></button></div></div></article>
  </div>
}

function EntityForm({ title, singular, onBack, onSave }: { title:string; singular:string; onBack:()=>void; onSave:()=>void }) {
  const transform = title.toLowerCase().includes('transformation')
  const distribution = title.toLowerCase().includes('distribution')
  return <div className="page-stack"><div className="page-heading"><div><button className="back-link" onClick={onBack}><ArrowLeft/> Retour à la liste</button><h1>{distribution ? 'Nouvelle distribution' : transform ? 'Nouvelle transformation' : `Ajouter un ${singular}`}</h1><p>Les champs marqués d’un astérisque sont obligatoires.</p></div><Badge tone="gray">Brouillon</Badge></div><form className="form-layout" onSubmit={e=>{e.preventDefault();onSave()}}><div className="panel form-panel"><div className="form-section"><span className="section-number">01</span><div><h2>Informations générales</h2><p>Identifiez clairement cet enregistrement.</p></div></div><div className="form-grid"><Field label={distribution?'Lot *':transform?'Produit ou lot *':'Nom *'} placeholder="Sélectionner ou saisir"/><Field label="Référence *" placeholder="Générée automatiquement"/><label className="field"><span>{transform?'Type de transformation *':'Catégorie *'}</span><select><option>Sélectionner une option</option><option>Production végétale</option><option>Transformation</option></select></label><Field label={distribution?'Date du mouvement *':'Date *'} type="date"/></div><label className="field"><span>Description</span><textarea placeholder="Ajoutez les informations utiles à la compréhension…"/></label><div className="form-divider"/><div className="form-section"><span className="section-number">02</span><div><h2>{distribution?'Mouvement et quantités':'Origine et traçabilité'}</h2><p>Ces données seront visibles dans le parcours de traçabilité.</p></div></div><div className="form-grid"><Field label={distribution?'Origine *':'Origine géographique *'} placeholder="Ex. Nantes"/><Field label={distribution?'Destination *':'Exploitation liée'} placeholder="Sélectionner"/><Field label="Quantité *" placeholder="0,00"/><label className="field"><span>Unité *</span><select><option>kg</option><option>L</option><option>unités</option></select></label></div><label className="upload-zone"><Upload/><strong>Déposer un justificatif</strong><span>PDF, PNG ou JPG · 10 Mo maximum</span><input type="file"/></label></div><aside className="form-aside"><div className="panel help-card"><div className="help-icon"><Sparkles/></div><h3>Qualité des données</h3><p>Des informations complètes renforcent la confiance dans votre traçabilité.</p><ul><li><Check/>Référence unique</li><li><Check/>Origine précise</li><li><Check/>Justificatif lisible</li></ul></div><div className="form-actions"><Button variant="ghost" onClick={onBack}>Annuler</Button><Button type="submit" icon={Check}>Enregistrer</Button></div></aside></form></div>
}

function DetailPage({ type, onBack, onEdit }: { type:string; onBack:()=>void; onEdit:()=>void }) {
  const [modal,setModal]=useState(false)
  return <div className="page-stack"><div className="page-heading"><div><button className="back-link" onClick={onBack}><ArrowLeft/> Retour à la liste</button><div className="detail-title"><h1>{type==='Lots'?'LOT-2506-118':type==='Certifications'?'CERT-2025-089':'Tomates anciennes'}</h1><Badge>{type==='Certifications'?'En attente':'Traçabilité complète'}</Badge></div><p>Créé le 18 juin 2025 · Dernière mise à jour il y a 12 min</p></div><div className="heading-actions"><Button variant="secondary" icon={Pencil} onClick={onEdit}>Modifier</Button><Button variant="danger" onClick={()=>setModal(true)}>Archiver</Button></div></div><div className="detail-grid"><article className="panel detail-main"><div className="detail-hero"><span className="detail-illustration"><Wheat/></span><div><span className="eyebrow">PRODUIT AGRICOLE · PRD-2025-0842</span><h2>Tomates anciennes</h2><p>Variétés anciennes cultivées en pleine terre selon des pratiques agroécologiques déclarées.</p></div></div><div className="info-grid"><div><span>Producteur</span><strong>Julien Moreau</strong><small>Ferme des Trois Chênes</small></div><div><span>Origine</span><strong>Vertou, France</strong><small>Loire-Atlantique</small></div><div><span>Quantité initiale</span><strong>480 kg</strong><small>Unité vérifiée</small></div><div><span>Date de récolte</span><strong>17 juin 2025</strong><small>Déclarée par le producteur</small></div></div></article><aside className="panel score-card"><div className="score-ring"><strong>92</strong><small>/100</small></div><h3>Parcours très complet</h3><p>6 étapes sur 6 sont documentées.</p><Button variant="secondary" icon={QrCode}>Voir la traçabilité</Button></aside></div><article className="panel timeline-panel"><div className="panel-head"><div><h2>Parcours de traçabilité</h2><p>Données disponibles, de la production à la distribution.</p></div><Badge>6 étapes vérifiées</Badge></div><div className="timeline">{[['Production','Ferme des Trois Chênes','17 juin · Vertou',Tractor],['Création du lot','LOT-2506-118 · 480 kg','18 juin · 08:42',Package],['Tri & calibrage','Atelier Saveurs & Sens','18 juin · 14:20',Factory],['Distribution','Vers Marché des Lices','19 juin · 06:15',Truck]].map(([t,d,date,icon],i)=>{const I=icon as LucideIcon;return <div key={t as string} className="timeline-item"><span><I/></span><div><small>ÉTAPE 0{i+1}</small><strong>{t as string}</strong><p>{d as string}</p><em>{date as string}</em></div></div>})}</div></article>{modal&&<Modal title="Archiver cet enregistrement ?" close={()=>setModal(false)}><p>Il ne sera plus disponible dans les listes actives, mais restera conservé dans le journal d’audit.</p><div className="modal-actions"><Button variant="ghost" onClick={()=>setModal(false)}>Annuler</Button><Button variant="danger" onClick={()=>setModal(false)}>Confirmer l’archivage</Button></div></Modal>}</div>
}

function ProfileSettings({ role, onToast }: { role:Role; onToast:(message:string)=>void }) {
  const [section,setSection]=useState<'profile'|'face'|'password'>('profile')
  const [photo,setPhoto]=useState<string>()
  const [faceEnabled,setFaceEnabled]=useState(false)
  const [faceStep,setFaceStep]=useState<'idle'|'scanning'|'done'>('idle')
  const [showPassword,setShowPassword]=useState(false)
  const cfg=roleConfig[role]
  const save=(message:string)=>{onToast(message)}
  const startFace=()=>{
    setFaceStep('scanning')
    window.setTimeout(()=>{setFaceStep('done');setFaceEnabled(true);save('Face ID configuré avec succès')},1600)
  }
  return <div className="page-stack profile-settings">
    <div className="page-heading"><div><div className="breadcrumbs">Compte <ChevronRight/> Paramètres du profil</div><h1>Paramètres du profil</h1><p>Gérez votre identité, vos accès biométriques et la sécurité de votre compte.</p></div><Badge tone="green"><ShieldCheck size={13}/> Compte vérifié</Badge></div>
    <div className="settings-layout">
      <aside className="panel settings-nav">
        <div className="settings-person"><span>{photo?<img src={photo} alt="Photo de profil"/>:cfg.name.split(' ').map(x=>x[0]).join('')}</span><div><strong>{cfg.name}</strong><small>{cfg.org}</small></div></div>
        <span className="settings-label">PARAMÈTRES DU COMPTE</span>
        <button className={section==='profile'?'active':''} onClick={()=>setSection('profile')}><User/><span><strong>Informations personnelles</strong><small>Identité, contact et photo</small></span><ChevronRight/></button>
        <button className={section==='face'?'active':''} onClick={()=>setSection('face')}><ScanFace/><span><strong>Face ID</strong><small>Connexion biométrique</small></span>{faceEnabled?<CheckCircle2 className="nav-success"/>:<ChevronRight/>}</button>
        <button className={section==='password'?'active':''} onClick={()=>setSection('password')}><LockKeyhole/><span><strong>Mot de passe</strong><small>Sécurité et accès</small></span><ChevronRight/></button>
        <div className="security-score"><ShieldCheck/><div><strong>Sécurité du compte</strong><span><i/><i/><i/><i className={faceEnabled?'filled':''}/></span><small>{faceEnabled?'Protection excellente':'Protection renforcée'}</small></div></div>
      </aside>
      <section className="panel settings-content">
        {section==='profile'&&<form onSubmit={e=>{e.preventDefault();save('Vos informations personnelles ont été mises à jour')}}>
          <div className="settings-head"><span className="settings-icon"><User/></span><div><h2>Informations personnelles</h2><p>Ces informations identifient votre compte sur NutriTrace.</p></div></div>
          <div className="photo-editor"><div className="profile-photo">{photo?<img src={photo} alt="Nouvelle photo de profil"/>:<span>{cfg.name.split(' ').map(x=>x[0]).join('')}</span>}<label><Camera/><input type="file" accept="image/png,image/jpeg" onChange={e=>{const file=e.target.files?.[0];if(file)setPhoto(URL.createObjectURL(file))}}/></label></div><div><strong>Photo de profil</strong><p>JPG ou PNG, 5 Mo maximum. Une image carrée est recommandée.</p><div><label className="mini-upload"><Upload/>Importer une photo<input type="file" accept="image/png,image/jpeg" onChange={e=>{const file=e.target.files?.[0];if(file)setPhoto(URL.createObjectURL(file))}}/></label>{photo&&<button type="button" onClick={()=>setPhoto(undefined)}>Supprimer</button>}</div></div></div>
          <div className="settings-divider"/>
          <div className="form-grid"><Field label="Prénom *" placeholder={cfg.name.split(' ')[0]}/><Field label="Nom *" placeholder={cfg.name.split(' ').slice(1).join(' ')}/><Field label="Adresse e-mail *" placeholder={`${cfg.name.toLowerCase().replace(' ','\.')}@exemple.fr`}/><Field label="Téléphone" placeholder="+33 6 12 34 56 78"/><Field label="Organisation" placeholder={cfg.org}/><label className="field"><span>Langue de l’interface</span><select><option>Français</option><option>English</option></select></label></div>
          <label className="field"><span>À propos</span><textarea placeholder="Présentez brièvement votre activité et votre rôle…"/></label>
          <div className="settings-actions"><span><CheckCircle2/>Dernière modification le 18 juin 2025</span><Button type="submit" icon={Check}>Enregistrer les modifications</Button></div>
        </form>}
        {section==='face'&&<div>
          <div className="settings-head"><span className="settings-icon purple"><ScanFace/></span><div><h2>Configurer Face ID</h2><p>Utilisez la reconnaissance faciale pour accéder plus rapidement à votre compte.</p></div>{faceEnabled&&<Badge>Activé</Badge>}</div>
          <div className="face-panel">
            <div className={`face-scanner ${faceStep==='scanning'?'scanning':''} ${faceStep==='done'?'done':''}`}><span className="corner tl"/><span className="corner tr"/><span className="corner bl"/><span className="corner br"/>{faceStep==='done'?<CheckCircle2/>:<ScanFace/>}{faceStep==='scanning'&&<i/>}</div>
            <h3>{faceStep==='scanning'?'Analyse en cours…':faceStep==='done'?'Face ID est prêt':faceEnabled?'Face ID est activé':'Enregistrez votre visage'}</h3>
            <p>{faceStep==='scanning'?'Gardez votre visage centré et restez immobile.':faceStep==='done'?'Vous pourrez désormais valider votre identité avec votre visage.':'Placez-vous dans un endroit éclairé et regardez directement la caméra.'}</p>
            {!faceEnabled&&faceStep!=='scanning'&&<Button icon={Camera} onClick={startFace}>Commencer la configuration</Button>}
            {faceEnabled&&<Button variant="secondary" icon={ScanFace} onClick={()=>{setFaceEnabled(false);setFaceStep('idle')}}>Reconfigurer Face ID</Button>}
          </div>
          <div className="biometric-note"><ShieldCheck/><div><strong>Vos données biométriques restent privées</strong><p>La représentation chiffrée de votre visage est stockée de manière sécurisée. NutriTrace ne conserve aucune photographie issue de la reconnaissance faciale.</p></div></div>
          {faceEnabled&&<div className="face-toggle"><div><strong>Connexion avec Face ID</strong><small>Demander Face ID lors de votre prochaine connexion</small></div><button className="toggle active" onClick={()=>setFaceEnabled(false)}><i/></button></div>}
        </div>}
        {section==='password'&&<form onSubmit={e=>{e.preventDefault();save('Votre mot de passe a été modifié en toute sécurité')}}>
          <div className="settings-head"><span className="settings-icon amber"><KeyRound/></span><div><h2>Changer le mot de passe</h2><p>Choisissez un mot de passe unique que vous n’utilisez nulle part ailleurs.</p></div></div>
          <div className="password-form">
            <label className="field"><span>Mot de passe actuel *</span><div className="password-input"><input type={showPassword?'text':'password'} placeholder="Saisissez votre mot de passe actuel"/><button type="button" onClick={()=>setShowPassword(!showPassword)}><Eye/></button></div><a>Mot de passe oublié ?</a></label>
            <label className="field"><span>Nouveau mot de passe *</span><div className="password-input"><input type={showPassword?'text':'password'} placeholder="8 caractères minimum"/><button type="button" onClick={()=>setShowPassword(!showPassword)}><Eye/></button></div></label>
            <div className="password-strength"><span><i/><i/><i/><i/></span><strong>Mot de passe robuste</strong></div>
            <div className="password-rules"><span><Check/>Au moins 8 caractères</span><span><Check/>Une lettre majuscule</span><span><Check/>Un chiffre</span><span><Check/>Un caractère spécial</span></div>
            <label className="field"><span>Confirmer le nouveau mot de passe *</span><div className="password-input"><input type={showPassword?'text':'password'} placeholder="Saisissez-le à nouveau"/><button type="button" onClick={()=>setShowPassword(!showPassword)}><Eye/></button></div></label>
          </div>
          <div className="session-warning"><AlertTriangle/><div><strong>Après la modification</strong><p>Vous serez déconnecté de tous les autres appareils pour protéger votre compte.</p></div></div>
          <div className="settings-actions"><span>Dernier changement il y a 4 mois</span><Button type="submit" icon={LockKeyhole}>Mettre à jour le mot de passe</Button></div>
        </form>}
      </section>
    </div>
  </div>
}

function AssistantPage({ role }: { role:Role }) {
  const [messages,setMessages]=useState(1)
  return <div className="assistant-layout"><aside className="chat-history"><Button icon={Plus}>Nouvelle analyse</Button><span>AUJOURD’HUI</span><button className="active"><MessageSquareText/>Analyse du lot 2506-118</button><button><MessageSquareText/>Données manquantes</button><span>7 DERNIERS JOURS</span><button><MessageSquareText/>Certificats à renouveler</button></aside><section className="assistant-main"><div className="assistant-head"><div className="bot-avatar"><Bot/></div><div><h1>Assistant IA NutriTrace</h1><p>Analyse adaptée à l’espace {role.toLowerCase()}</p></div><Badge tone="blue">Démonstration</Badge></div><div className="chat"><div className="ai-message"><span><Bot/></span><div><p>Bonjour {roleConfig[role].name.split(' ')[0]}. Je peux analyser vos données de traçabilité et identifier les éléments à vérifier.</p><div className="suggestions"><button>Quelles informations sont manquantes ?</button><button>Analyser le lot LOT-2506-118</button><button>Voir les certificats expirants</button></div></div></div>{messages>1&&<><div className="user-message">Analyse le lot LOT-2506-118 et résume les points d’attention.</div><div className="ai-message"><span><Sparkles/></span><div><div className="demo-label"><AlertTriangle/>Résultat fictif — exemple de démonstration</div><p>Le parcours contient <strong>6 étapes documentées</strong>. Une différence de 8 kg existe entre la quantité après tri et la quantité distribuée.</p><div className="analysis-card"><div><span className="alert-icon amber"><AlertTriangle/></span><span><strong>Quantité à vérifier</strong><small>Écart de 1,7% entre deux étapes</small></span><Badge tone="amber">Modéré</Badge></div><button>Consulter le lot <ArrowRight/></button></div><small className="sources">Sources : lot LOT-2506-118, transformation TRF-2025-301, mouvement MVT-2025-441</small></div></div></>}</div><div className="chat-input"><button><Upload/></button><input placeholder="Posez une question sur vos données…"/><Button icon={ArrowRight} onClick={()=>setMessages(2)}>Envoyer</Button><small>Les analyses sont indicatives et doivent être vérifiées par un humain.</small></div></section></div>
}

function DesignSystem({ onBack }: { onBack:()=>void }) {
  return <div className="design-page"><header><Logo/><div><Button variant="ghost" icon={ArrowLeft} onClick={onBack}>Retour au prototype</Button><Badge>v1.0 · Juin 2025</Badge></div></header><div className="ds-hero"><span className="eyebrow">01 — DESIGN SYSTEM</span><h1>Clair, naturel, digne de confiance.</h1><p>Des fondations communes pour offrir une expérience cohérente à chaque acteur de la chaîne alimentaire.</p></div><section className="ds-section"><div><span>01</span><h2>Fondations</h2></div><div className="color-grid">{[['Forêt','#1B4332'],['Principal','#2D6A4F'],['Sauge','#95D5B2'],['Brume','#F0F7F1'],['Sable','#F7F4EA'],['Encre','#1F2937']].map(([n,c])=><article key={n}><i style={{background:c}}/><strong>{n}</strong><code>{c}</code></article>)}</div></section><section className="ds-section"><div><span>02</span><h2>Composants</h2></div><div className="component-demo"><div className="demo-row"><Button>Action principale</Button><Button variant="secondary">Action secondaire</Button><Button variant="ghost">Action tertiaire</Button><Button variant="danger">Action sensible</Button></div><div className="demo-row"><Badge>Vérifié</Badge><Badge tone="amber">À compléter</Badge><Badge tone="red">Erreur</Badge><Badge tone="blue">Information</Badge><Badge tone="gray">Brouillon</Badge></div><div className="demo-fields"><Field label="Champ par défaut" placeholder="Saisir une valeur"/><Field label="Champ avec erreur" placeholder="Référence" error="Cette référence existe déjà"/></div></div></section><section className="ds-section"><div><span>03</span><h2>États système</h2></div><div className="state-grid"><article><span className="state-icon success"><CheckCircle2/></span><h3>Opération réussie</h3><p>Les données ont été enregistrées.</p></article><article><span className="state-icon warning"><AlertTriangle/></span><h3>Attention requise</h3><p>Une information doit être vérifiée.</p></article><article><span className="state-icon empty"><Database/></span><h3>Aucune donnée</h3><p>Commencez par créer un élément.</p></article></div></section></div>
}

function Modal({ title, close, children }: { title:string; close:()=>void; children:React.ReactNode }) {
  return <div className="modal-backdrop" onMouseDown={close}><div className="modal" onMouseDown={e=>e.stopPropagation()}><div className="modal-head"><span className="modal-icon"><AlertTriangle/></span><div><h2>{title}</h2><small>Cette action peut être modifiée ultérieurement.</small></div><button onClick={close}><X/></button></div>{children}</div></div>
}

function AppShell({ initialRole, onMode }: { initialRole:Role; onMode:(m:Mode)=>void }) {
  const [role,setRole]=useState<Role>(initialRole)
  const [active,setActive]=useState('Tableau de bord')
  const [menu,setMenu]=useState(false)
  const [toast,setToast]=useState('')
  const content = useMemo(() => {
    if (active === 'Tableau de bord') return <Dashboard role={role} navigate={setActive}/>
    if (active.includes('Assistant IA') || active.includes('Analyses')) return <AssistantPage role={role}/>
    if (active === 'Mon profil' || active === 'Paramètres') return <ProfileSettings role={role} onToast={s=>{setToast(s);setTimeout(()=>setToast(''),3000)}}/>
    return <EntityPage title={active} onToast={s=>{setToast(s);setTimeout(()=>setToast(''),3000)}}/>
  }, [active,role])
  return <div className="app-shell"><Sidebar role={role} active={active} setActive={setActive} open={menu} close={()=>setMenu(false)} onMode={onMode}/><div className="app-main"><Topbar role={role} onMenu={()=>setMenu(true)} onRole={r=>{setRole(r);setActive('Tableau de bord')}}/><main className="content">{content}</main></div><button className="ds-fab" onClick={()=>onMode('design')}><Sparkles/>Design System</button>{toast&&<div className="toast"><CheckCircle2/><span><strong>Modification enregistrée</strong><small>{toast}</small></span><button onClick={()=>setToast('')}><X/></button></div>}</div>
}

export default function App() {
  const [mode,setMode]=useState<Mode>('app')
  const [role,setRole]=useState<Role>('Administrateur')
  if (mode === 'login') return <AuthScreen onMode={setMode} onEnter={r=>{setRole(r);setMode('app')}}/>
  if (mode === 'register') return <AuthScreen register onMode={setMode} onEnter={r=>{setRole(r);setMode('app')}}/>
  if (mode === 'public') return <PublicHome onMode={setMode}/>
  if (mode === 'design') return <DesignSystem onBack={()=>setMode('app')}/>
  return <AppShell initialRole={role} onMode={setMode}/>
}

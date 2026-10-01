@extends('layouts.base')

@section('title', 'Welcome to ORWeMS — Orphans Registration & Welfare Management System')

@section('content')
    {{-- Public Navigation Header --}}
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom py-3 shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="{{ url('/') }}">
                <i class="bi bi-shield-heart fs-3 text-primary me-2"></i>
                <div>
                    <span class="fw-bold text-dark fs-5">ORWeMS</span>
                    <span class="badge bg-primary-subtle text-primary ms-2 small">WICHOV</span>
                </div>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#landingNavbar" aria-controls="landingNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="landingNavbar">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center gap-lg-3">
                    <li class="nav-item">
                        <a class="nav-link" href="#overview">Architecture</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#guidance">Islamic Guidance</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#mission">Mission in Action</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#scope">Pilot Scope</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#research">Research &amp; IWAE</a>
                    </li>
                    <li class="nav-item ms-lg-2">
                        <a href="{{ route('dashboard') }}" class="btn btn-primary d-inline-flex align-items-center gap-1 shadow-sm">
                            <i class="bi bi-speedometer2"></i>
                            <span>Access Dashboard</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    {{-- Hero Section --}}
    <section class="landing-hero py-5">
        <div class="container py-lg-4">
            <div class="row align-items-center g-5">
                <div class="col-lg-7">
                    <div class="hero-pill">
                        <i class="bi bi-award-fill"></i>
                        <span>Welfare Initiative for Children, Orphans and Vulnerable (WICHOV)</span>
                    </div>

                    <h1 class="display-5 fw-bold text-dark lh-sm mb-3">
                        Orphans Registration &amp; Welfare Management System
                    </h1>

                    <p class="lead text-secondary mb-4">
                        A specialized humanitarian information-management and welfare decision-support platform designed for systematic vulnerability profiling, targeted interventions, and empirical research.
                    </p>

                    <div class="d-flex flex-wrap gap-3">
                        <a href="{{ route('dashboard') }}" class="btn btn-primary btn-lg px-4 shadow-sm">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Enter Application Shell
                        </a>
                        <a href="#guidance" class="btn btn-outline-secondary btn-lg px-4">
                            Explore Guidance &amp; Mission
                        </a>
                    </div>

                    <div class="row g-3 mt-4 pt-3 border-top">
                        <div class="col-sm-4">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-check2-circle text-success fs-4"></i>
                                <div class="small">
                                    <div class="fw-bold">Humanitarian NGO</div>
                                    <span class="text-muted">Field operations ready</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-check2-circle text-success fs-4"></i>
                                <div class="small">
                                    <div class="fw-bold">PhD Research</div>
                                    <span class="text-muted">Rigorous data model</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-check2-circle text-success fs-4"></i>
                                <div class="small">
                                    <div class="fw-bold">cPanel Deployable</div>
                                    <span class="text-muted">Production-oriented</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="card shadow border-0 p-4 bg-white">
                        <div class="d-flex align-items-center justify-content-between pb-3 mb-3 border-bottom">
                            <div>
                                <h5 class="fw-bold mb-0">Platform Overview</h5>
                                <small class="text-muted">Architecture &amp; Mission</small>
                            </div>
                            <span class="badge bg-success">Phase 1B Ready</span>
                        </div>

                        <div class="mb-3">
                            <label class="small text-muted text-uppercase fw-semibold">Organisation</label>
                            <p class="mb-0 fw-semibold text-dark">WICHOV</p>
                            <small class="text-secondary">Welfare Initiative for Children, Orphans and Vulnerable</small>
                        </div>

                        <div class="mb-3">
                            <label class="small text-muted text-uppercase fw-semibold">Target Environment</label>
                            <p class="mb-0 text-dark">Laravel 12 &bull; Blade &bull; Bootstrap 5 &bull; MySQL 8.4</p>
                        </div>

                        <div class="mb-3">
                            <label class="small text-muted text-uppercase fw-semibold">Key Capabilities (Planned)</label>
                            <ul class="list-unstyled small mb-0">
                                <li class="py-1"><i class="bi bi-dot text-primary fs-5 align-middle"></i>Centralized Beneficiary Registry</li>
                                <li class="py-1"><i class="bi bi-dot text-primary fs-5 align-middle"></i>Multidimensional Welfare Assessments</li>
                                <li class="py-1"><i class="bi bi-dot text-primary fs-5 align-middle"></i>IWAE Algorithmic Decision-Support</li>
                                <li class="py-1"><i class="bi bi-dot text-primary fs-5 align-middle"></i>Strict Child Safeguarding &amp; Audit Logs</li>
                            </ul>
                        </div>

                        <div class="p-3 bg-light rounded small text-muted border">
                            <i class="bi bi-info-circle me-1 text-primary"></i>
                            Built for sustainable humanitarian operations in Nigeria, fully decoupled from hard-coded geographical boundaries.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Platform Pillars Section --}}
    <section id="overview" class="py-5 bg-white">
        <div class="container py-lg-4">
            <div class="text-center max-w-2xl mx-auto mb-5">
                <span class="text-primary fw-bold text-uppercase small">Core Architecture</span>
                <h2 class="fw-bold text-dark mt-1">Foundational Humanitarian Architecture</h2>
                <p class="text-muted">Built from the ground up for high-integrity case management and decision science.</p>
            </div>

            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100 border p-4 shadow-sm">
                        <div class="stat-card-icon bg-primary-subtle text-primary mb-3">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <h5 class="fw-bold text-dark">Beneficiary Management</h5>
                        <p class="text-secondary small">
                            Comprehensive intake and profile management for orphans, vulnerable children, and their primary caregivers, supporting guardian history and kinship arrangements.
                        </p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card h-100 border p-4 shadow-sm">
                        <div class="stat-card-icon bg-success-subtle text-success mb-3">
                            <i class="bi bi-cpu-fill"></i>
                        </div>
                        <h5 class="fw-bold text-dark">Welfare Decision Support</h5>
                        <p class="text-secondary small">
                            Algorithmic scoring using the Index of Vulnerability and Welfare Assessment (IWAE) to eliminate subjectivity and prioritize emergency assistance fairly.
                        </p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card h-100 border p-4 shadow-sm">
                        <div class="stat-card-icon bg-info-subtle text-info mb-3">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <h5 class="fw-bold text-dark">Governance &amp; Privacy</h5>
                        <p class="text-secondary small">
                            Role-based access control, cryptographic data protection, and immutable audit logs ensuring compliance with child protection standards and research ethics.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Qur'anic Guidance Section --}}
    <section id="guidance" class="py-5 bg-light border-top border-bottom">
        <div class="container py-lg-4">
            <div class="text-center mx-auto mb-5" style="max-width: 720px;">
                <span class="text-success fw-bold text-uppercase small">Moral &amp; Ethical Foundation</span>
                <h2 class="fw-bold text-dark mt-1">Qur'anic Guidance on Caring for Orphans</h2>
                <p class="text-muted">
                    In Islamic ethical teachings, the protection, dignity, and holistic welfare of orphans represent a profound communal obligation and sacred trust.
                </p>
            </div>

            <div class="row g-4">
                {{-- Surah Ad-Duha 93:9 --}}
                <div class="col-lg-4 col-md-6">
                    <div class="guidance-card">
                        <div class="guidance-arabic-box">
                            <span class="badge bg-success-subtle text-success mb-2 small">Qur'an 93:9</span>
                            <p class="arabic-text mb-0" lang="ar">فَأَمَّا ٱلْيَتِيمَ فَلَا تَقْهَرْ</p>
                        </div>
                        <div class="p-4 d-flex flex-column justify-content-between flex-grow-1">
                            <blockquote class="blockquote fs-6 text-dark mb-3">
                                &ldquo;So as for the orphan, do not oppress [him].&rdquo;
                            </blockquote>
                            <div class="border-top pt-2">
                                <small class="text-muted fw-semibold">
                                    <i class="bi bi-book me-1 text-success"></i> Qur'an 93:9 — Surah Ad-Duha
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Surah Al-Baqarah 2:220 --}}
                <div class="col-lg-4 col-md-6">
                    <div class="guidance-card">
                        <div class="guidance-arabic-box">
                            <span class="badge bg-success-subtle text-success mb-2 small">Qur'an 2:220</span>
                            <p class="arabic-text mb-0" lang="ar">وَيَسْـَٔلُونَكَ عَنِ ٱلْيَتَـٰمَىٰ ۖ قُلْ إِصْلَاحٌۭ لَّهُمْ خَيْرٌۭ ۖ وَإِن تُخَالِطُوهُمْ فَإِخْوَٰنُكُمْ ۚ وَٱللَّهُ يَعْلَمُ ٱلْمُفْسِدَ مِنَ ٱلْمُصْلِحِ</p>
                        </div>
                        <div class="p-4 d-flex flex-column justify-content-between flex-grow-1">
                            <blockquote class="blockquote fs-6 text-dark mb-3">
                                &ldquo;And they ask you about orphans. Say, &lsquo;Improvement for them is best. And if you mix your affairs with theirs &mdash; they are your brothers. And Allah knows the corrupter from the amender.&rsquo;&rdquo;
                            </blockquote>
                            <div class="border-top pt-2">
                                <small class="text-muted fw-semibold">
                                    <i class="bi bi-book me-1 text-success"></i> Qur'an 2:220 — Surah Al-Baqarah
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Surah Al-Ma'un 107:1-3 --}}
                <div class="col-lg-4 col-md-12">
                    <div class="guidance-card">
                        <div class="guidance-arabic-box">
                            <span class="badge bg-success-subtle text-success mb-2 small">Qur'an 107:1–3</span>
                            <p class="arabic-text mb-0" lang="ar">أَرَءَيْتَ ٱلَّذِى يُكَذِّبُ بِٱلدِّينِ &bull; فَذَٰلِكَ ٱلَّذِى يَدُعُّ ٱلْيَتِيمَ &bull; وَلَا يَحُضُّ عَلَىٰ طَعَامِ ٱلْمِسْكِينِ</p>
                        </div>
                        <div class="p-4 d-flex flex-column justify-content-between flex-grow-1">
                            <blockquote class="blockquote fs-6 text-dark mb-3">
                                &ldquo;Have you seen the one who denies the Recompense? For that is the one who drives away the orphan, and does not encourage the feeding of the poor.&rdquo;
                            </blockquote>
                            <div class="border-top pt-2">
                                <small class="text-muted fw-semibold">
                                    <i class="bi bi-book me-1 text-success"></i> Qur'an 107:1–3 — Surah Al-Ma'un
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Prophetic Guidance Section --}}
    <section class="py-5 bg-white border-bottom">
        <div class="container py-lg-4">
            <div class="row align-items-center g-5">
                <div class="col-lg-6">
                    <span class="text-success fw-bold text-uppercase small">Sunnah &amp; Hadith</span>
                    <h2 class="fw-bold text-dark mt-1 mb-3">Prophetic Guidance</h2>
                    <p class="text-secondary mb-4">
                        The Prophet Muhammad ﷺ explicitly elevated the guardianship and sponsorship of orphans to the highest ranks of moral virtue and spiritual proximity, underscoring compassionate communal responsibility.
                    </p>

                    <div class="hadith-quote-box mb-4">
                        <p class="arabic-text text-dark mb-3 fs-5" lang="ar">
                            قَالَ رَسُولُ اللَّهِ ﷺ: «أَنَا وَكَافِلُ الْيَتِيمِ فِي الْجَنَّةِ هَكَذَا» وَأَشَارَ بِالسَّبَّابَةِ وَالْوُسْطَىٰ وَفَرَّجَ بَيْنَهُمَا شَيْئًا
                        </p>
                        <blockquote class="blockquote fs-6 text-dark mb-2 fst-italic">
                            &ldquo;The Messenger of Allah ﷺ said: &lsquo;I and the one who sponsors an orphan will be like this in Paradise,&rsquo; and he indicated with his index and middle fingers, separating between them slightly.&rdquo;
                        </blockquote>
                        <div class="text-muted small fw-bold">
                            &mdash; Sahih al-Bukhari 5304 (also recorded in Sahih al-Bukhari 6005)
                        </div>
                    </div>

                    <div class="p-3 bg-light rounded small text-secondary border">
                        <i class="bi bi-info-circle text-success me-1"></i>
                        <em>Contextual Note:</em> In Islamic jurisprudence, orphan sponsorship (<em>Kafalah</em>) signifies compassionate guardianship, financial maintenance, moral mentorship, and protection of the child&rsquo;s identity and family lineage.
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="welfare-visual-card shadow-sm">
                        <img src="{{ asset('images/landing/orphan-care.svg') }}" alt="Dignified orphan care and holistic sponsorship" class="img-fluid" loading="lazy">
                        <div class="p-4">
                            <span class="badge bg-primary-subtle text-primary mb-2">Ethical Imperative</span>
                            <h5 class="fw-bold text-dark mb-2">Preserving Dignity &amp; Wholeness</h5>
                            <p class="text-secondary small mb-0">
                                Sponsoring and supporting an orphan extends beyond material aid &mdash; it encompasses emotional security, family preservation, and cultivating an environment where every child thrives with honour.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- From Compassion to Responsible Action Section --}}
    <section id="mission" class="py-5 bg-light border-bottom">
        <div class="container py-lg-4">
            <div class="text-center mx-auto mb-5" style="max-width: 760px;">
                <span class="text-primary fw-bold text-uppercase small">Humanitarian Execution</span>
                <h2 class="fw-bold text-dark mt-1">From Compassion to Responsible Action</h2>
                <p class="text-secondary">
                    Meaningful orphan welfare requires more than good intentions. Genuine care demands methodical systems, evidence-based assessments, and transparent stewardship so that vulnerable children receive equitable, lasting support.
                </p>
            </div>

            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border p-4 shadow-sm bg-white">
                        <div class="text-primary mb-3 fs-3">
                            <i class="bi bi-person-vcard"></i>
                        </div>
                        <h6 class="fw-bold text-dark">Responsible Registration</h6>
                        <p class="text-secondary small mb-0">
                            Ethical identification and verification that capture comprehensive household context while strictly safeguarding vulnerable identities.
                        </p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border p-4 shadow-sm bg-white">
                        <div class="text-primary mb-3 fs-3">
                            <i class="bi bi-clipboard2-pulse"></i>
                        </div>
                        <h6 class="fw-bold text-dark">Individualized Needs</h6>
                        <p class="text-secondary small mb-0">
                            Recognizing that each child possesses unique circumstances across shelter, nutrition, psychological health, and caregiver stability.
                        </p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border p-4 shadow-sm bg-white">
                        <div class="text-primary mb-3 fs-3">
                            <i class="bi bi-diagram-3"></i>
                        </div>
                        <h6 class="fw-bold text-dark">Coordinated Support</h6>
                        <p class="text-secondary small mb-0">
                            Aligning donor resources, field worker visits, and community networks into coherent, non-duplicative welfare interventions.
                        </p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border p-4 shadow-sm bg-white">
                        <div class="text-primary mb-3 fs-3">
                            <i class="bi bi-shield-lock"></i>
                        </div>
                        <h6 class="fw-bold text-dark">Data Protection &amp; Ethics</h6>
                        <p class="text-secondary small mb-0">
                            Enforcing role-based access, cryptographic security, and immutable audit logs to protect children&rsquo;s sensitive records from misuse.
                        </p>
                    </div>
                </div>
            </div>

            <div class="row g-4 mt-2">
                <div class="col-md-6">
                    <div class="welfare-visual-card">
                        <img src="{{ asset('images/landing/education-support.svg') }}" alt="Educational support and learning opportunities for vulnerable children" class="img-fluid" loading="lazy">
                        <div class="p-3">
                            <h6 class="fw-bold text-dark mb-1">Education &amp; Skill Development</h6>
                            <p class="text-secondary small mb-0">
                                Aimed at supporting educational access, school retention, and skill-building opportunities to dismantle generational vulnerability.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="welfare-visual-card">
                        <img src="{{ asset('images/landing/community-care.svg') }}" alt="Safe community care, solidarity, and coordinated humanitarian assistance" class="img-fluid" loading="lazy">
                        <div class="p-3">
                            <h6 class="fw-bold text-dark mb-1">Safe Community &amp; Solidarity</h6>
                            <p class="text-secondary small mb-0">
                                Grounded in community engagement, kinship strengthening, and transparent accountability across all field operations.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Pilot Operational Scope --}}
    <section id="scope" class="py-5 bg-white border-bottom">
        <div class="container py-lg-4">
            <div class="row align-items-center g-5">
                <div class="col-lg-6">
                    <span class="text-primary fw-bold text-uppercase small">Field Operations</span>
                    <h2 class="fw-bold text-dark mt-1 mb-3">Initial Pilot Scope</h2>
                    <p class="text-secondary">
                        ORWeMS is launching its initial field pilot in three critical jurisdictions. Crucially, the platform data model is entirely dynamic &mdash; states, LGAs, and wards are stored as reference entities rather than hard-coded constants, enabling seamless scale nationwide.
                    </p>

                    <div class="list-group shadow-sm">
                        <div class="list-group-item d-flex align-items-center gap-3 p-3">
                            <span class="badge bg-primary rounded-circle p-2"><i class="bi bi-geo-alt fs-5"></i></span>
                            <div>
                                <h6 class="mb-0 fw-bold">Niger State</h6>
                                <small class="text-muted">Target initial pilot jurisdiction for North Central regional deployment</small>
                            </div>
                        </div>
                        <div class="list-group-item d-flex align-items-center gap-3 p-3">
                            <span class="badge bg-primary rounded-circle p-2"><i class="bi bi-geo-alt fs-5"></i></span>
                            <div>
                                <h6 class="mb-0 fw-bold">Nasarawa State</h6>
                                <small class="text-muted">Target initial pilot jurisdiction for community welfare profiling</small>
                            </div>
                        </div>
                        <div class="list-group-item d-flex align-items-center gap-3 p-3">
                            <span class="badge bg-primary rounded-circle p-2"><i class="bi bi-geo-alt fs-5"></i></span>
                            <div>
                                <h6 class="mb-0 fw-bold">Federal Capital Territory (FCT)</h6>
                                <small class="text-muted">Urban and peri-urban vulnerable child vulnerability tracking</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6" id="research">
                    <div class="card p-4 border shadow-sm">
                        <span class="text-uppercase small fw-bold text-secondary mb-1">Academic &amp; Humanitarian Synergy</span>
                        <h4 class="fw-bold text-dark mb-3">Empirical Research Foundation</h4>
                        <p class="text-secondary small">
                            ORWeMS serves a dual humanitarian and academic mandate. As part of ongoing PhD research, the platform implements standardized vulnerability assessment instruments to measure intervention efficacy across longitudinal cohorts.
                        </p>

                        <div class="border rounded p-3 bg-light mb-3">
                            <div class="fw-bold small text-dark mb-1"><i class="bi bi-journal-code text-primary me-1"></i> IWAE Framework</div>
                            <div class="small text-muted">
                                Multi-dimensional assessment engine integrating health, education, nutrition, shelter, and psychosocial vulnerability indices.
                            </div>
                        </div>

                        <div id="governance" class="border rounded p-3 bg-light">
                            <div class="fw-bold small text-dark mb-1"><i class="bi bi-lock-fill text-success me-1"></i> Responsible Data Principles</div>
                            <div class="small text-muted">
                                Strict adherence to non-harm, anonymized research extraction, and strict segregation between operational identifiers and analytical datasets.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Call to Action / Footer --}}
    <footer class="bg-dark text-white py-5">
        <div class="container">
            <div class="row g-4 align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="bi bi-shield-heart text-primary fs-3"></i>
                        <h4 class="fw-bold mb-0 text-white">ORWeMS</h4>
                        <span class="badge bg-secondary">WICHOV</span>
                    </div>
                    <p class="text-white-50 small mb-0">
                        Orphans Registration and Welfare Management System &bull; Welfare Initiative for Children, Orphans and Vulnerable.
                        <br>Dedicated to safeguarding vulnerable children through empirical evidence and systematic care.
                    </p>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="{{ route('dashboard') }}" class="btn btn-primary px-4 py-2">
                        <i class="bi bi-speedometer2 me-1"></i> Launch Application
                    </a>
                </div>
            </div>

            <hr class="border-secondary my-4">

            <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between text-white-50 small gap-2">
                <div>&copy; {{ date('Y') }} WICHOV. All rights reserved.</div>
                <div>
                    Designed &amp; Developed by
                    <a href="https://qafamstech.com.ng/" target="_blank" rel="noopener noreferrer" class="text-white-50 text-decoration-underline">
                        Qafam Software Technology (Qafam Soft-Tech)
                    </a>
                </div>
            </div>
        </div>
    </footer>
@endsection
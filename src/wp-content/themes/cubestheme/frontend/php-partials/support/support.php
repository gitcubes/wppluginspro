<section class="hero-section support-hero">
    <div class="container">
        <div class="lead-content-holder animation" data-animation="slideUp" data-delay="0.15s">
            <span class="label">Support</span>
            <h1 class="lead-title">Get support for your plugins</h1>
            <p class="lead-description">
                Technical issues, billing questions or pre-sales doubts - this is the fastest way to reach the people
                who actually build plugins like WSH Views Counter PRO. We usually respond in business hours
                (Europe/Belgrade time).
            </p>
        </div>
    </div>
</section>

<section class="support-request">
    <div class="container">
        <div class="support-layout">
            <div class="support-request-panel box animation" data-animation="slideUp" data-delay="0.12s">
                <div class="content">
                    <div class="support-panel-intro">
                        <h2>Send a support request</h2>
                        <p>
                            The more detail you share, the easier it is for us to reproduce and fix the issue. PRO
                            customers are answered first.
                        </p>
                    </div>

                    <form action="" class="support-request-form">
                        <div class="support-field-grid">
                            <div class="support-field">
                                <div class="support-field-head">
                                    <label for="support-name">Your name</label>
                                </div>
                                <input type="text" id="support-name" name="support_name" placeholder="Ana" required>
                            </div>

                            <div class="support-field">
                                <div class="support-field-head">
                                    <label for="support-email">Email <span class="support-label-note">(Use the email you
                                            purchased with)</span></label>
                                </div>
                                <input type="email" id="support-email" name="support_email" placeholder="Markovic"
                                    required>
                            </div>

                            <div class="support-field">
                                <div class="support-field-head">
                                    <label for="support-type">What do you need help with?</label>
                                </div>
                                <div class="support-select-field">
                                    <select id="support-type" name="support_type" required>
                                        <option value="" selected>Select type...</option>
                                        <option value="technical">Technical issue</option>
                                        <option value="billing">Billing question</option>
                                        <option value="pre-sales">Pre-sales question</option>
                                    </select>
                                    <span class="support-select-icon" aria-hidden="true">
                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none"
                                            xmlns="http://www.w3.org/2000/svg">
                                            <path d="M12.6673 6L8.00065 10.6667L3.33398 6" stroke="currentColor"
                                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </span>
                                </div>
                            </div>

                            <div class="support-field">
                                <div class="support-field-head">
                                    <label for="support-plugin">Which plugin or suite?</label>
                                </div>
                                <div class="support-select-field">
                                    <select id="support-plugin" name="support_plugin" required>
                                        <option value="" selected>Select...</option>
                                        <option value="views-counter-pro">WSH Views Counter PRO</option>
                                        <option value="editor-enhancer">WSH Editor Enhancer</option>
                                        <option value="news-suite">News Portal Suite</option>
                                        <option value="woo-suite">WooCommerce Growth Suite</option>
                                    </select>
                                    <span class="support-select-icon" aria-hidden="true">
                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none"
                                            xmlns="http://www.w3.org/2000/svg">
                                            <path d="M12.6673 6L8.00065 10.6667L3.33398 6" stroke="currentColor"
                                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </span>
                                </div>
                            </div>

                            <div class="support-field">
                                <div class="support-field-head">
                                    <label for="support-site">Site URL / domain <span
                                            class="support-label-note">(Optional, but very helpful)</span></label>
                                </div>
                                <input type="url" id="support-site" name="support_site"
                                    placeholder="https://www.yoursite.com">
                            </div>

                            <div class="support-field">
                                <div class="support-field-head">
                                    <label>Priority <span class="support-label-note">(Be honest - this helps
                                            everyone)</span></label>
                                </div>
                                <div class="support-priority-group">
                                    <label class="support-priority-option">
                                        <input type="radio" name="support_priority" value="normal" checked>
                                        <span class="support-priority-indicator">
                                            <svg width="10" height="10" viewBox="0 0 12 12" fill="none"
                                                xmlns="http://www.w3.org/2000/svg">
                                                <path d="M2.5 6L4.8 8.3L9.5 3.5" stroke="white" stroke-width="1.6"
                                                    stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                        </span>
                                        <span class="support-priority-text">Normal</span>
                                    </label>
                                    <label class="support-priority-option">
                                        <input type="radio" name="support_priority" value="high">
                                        <span class="support-priority-indicator">
                                            <svg width="10" height="10" viewBox="0 0 12 12" fill="none"
                                                xmlns="http://www.w3.org/2000/svg">
                                                <path d="M2.5 6L4.8 8.3L9.5 3.5" stroke="white" stroke-width="1.6"
                                                    stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                        </span>
                                        <span class="support-priority-text">High (site broken)</span>
                                    </label>
                                </div>
                            </div>

                            <div class="support-field support-field-wide">
                                <div class="support-field-head">
                                    <label for="support-subject">Subject</label>
                                </div>
                                <input type="text" id="support-subject" name="support_subject"
                                    placeholder="Example: Views not counting on WooCommerce product pages" required>
                            </div>

                            <div class="support-field support-field-wide">
                                <div class="support-field-head support-field-head-split">
                                    <label for="support-issue">Describe the issue</label>
                                    <span class="support-field-hint">Steps to reproduce, what you expected, what you
                                        got</span>
                                </div>
                                <textarea id="support-issue" name="support_issue" rows="5"
                                    placeholder="1) Go to ...&#10;2) Click on...&#10;3) Expected result:&#10;4) Actual result:"
                                    required></textarea>
                            </div>

                            <div class="support-field support-field-wide">
                                <div class="support-field-head support-field-head-split">
                                    <label for="support-environment">Environment / logs</label>
                                    <span class="support-field-hint">Optional</span>
                                </div>
                                <textarea id="support-environment" name="support_environment" rows="6"
                                    placeholder="WordPress version, PHP version, plugin version, theme..."></textarea>
                            </div>
                        </div>

                        <label class="support-consent">
                            <input type="checkbox" name="support_privacy" required>
                            <span class="support-consent-indicator">
                                <svg width="10" height="10" viewBox="0 0 12 12" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path d="M2.5 6L4.8 8.3L9.5 3.5" stroke="white" stroke-width="1.6"
                                        stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>
                            <span class="support-consent-text">I agree that my data will be processed in order to answer
                                this request. Read our Privacy Policy.</span>
                        </label>

                        <button type="submit" class="btn btn-primary">Submit Request</button>

                        <p class="support-submit-note">
                            You'll receive a confirmation email with a ticket ID. PRO customers are served first, but we
                            read every message.
                        </p>
                    </form>
                </div>
            </div>

            <aside class="support-guide-panel box animation" data-animation="slideUp" data-delay="0.18s">
                <div class="content">
                    <div class="support-panel-intro">
                        <h3>Make your ticket "developer-friendly"</h3>
                        <p>
                            We're not a generic helpdesk. Your ticket goes straight to someone who actually ships code.
                            These details help a lot:
                        </p>
                    </div>

                    <div class="support-guide-list">
                        <div class="support-guide-card">
                            <h4>1. System information</h4>
                            <ul>
                                <li>WordPress version, PHP version, theme name.</li>
                                <li>Exact plugin version (e.g. WSH Views Counter PRO 1.3.0).</li>
                                <li>Whether the issue exists with all other plugins disabled and a default theme.</li>
                            </ul>
                        </div>

                        <div class="support-guide-card">
                            <h4>2. Steps to reproduce</h4>
                            <ul>
                                <li>Numbered list of actions (clicks / pages).</li>
                                <li>Exact URL where the problem shows up.</li>
                                <li>What you expected vs what actually happens.</li>
                            </ul>
                        </div>

                        <div class="support-guide-card">
                            <h4>3. Access & staging</h4>
                            <ul>
                                <li>If possible, create a staging site with the same issue.</li>
                                <li>Never send passwords or API keys in plain text via this form.</li>
                                <li>If we need access, we'll ask for a safe way to share credentials.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </aside>
        </div>

        <div class="card-grid">
            <div class="single-box box animation" data-animation="slideUp" data-delay="0.12s">
                <div class="content">
                    <div class="border">
                        <div class="image-holder">
                            <span>🤝</span>
                        </div>
                    </div>
                    <h4>Before you contact us</h4>
                    <ul>
                        <li>
                            Check the <a href="#">documentation</a> for your plugin.
                        </li>
                        <li>
                            Look at <a href="#">common troubleshooting steps.</a>
                        </li>
                        <li>
                            Make sure all plugins and WordPress core are up to date.
                        </li>
                    </ul>
                </div>
            </div>
            <div class="single-box box animation" data-animation="slideUp" data-delay="0.2s">
                <div class="content">
                    <div class="border">
                        <div class="image-holder">
                            <span>🤝</span>
                        </div>
                    </div>
                    <h4>Licenses & billing</h4>
                    <ul>
                        <li>
                            For license keys, renewals and invoices use your <a href="#">Account dashboard.</a>
                        </li>
                        <li>
                            If you can’t access your account, use the email address you purchased with.
                        </li>
                        <li>
                            We never ask for your WordPress admin password.
                        </li>
                    </ul>
                </div>
            </div>
            <div class="single-box box animation" data-animation="slideUp" data-delay="0.28s">
                <div class="content">
                    <div class="border">
                        <div class="image-holder">
                            <span>🤝</span>
                        </div>
                    </div>
                    <h4>Production incidents</h4>
                    <ul>
                        <li>
                            Select High priority only if your production site is broken.
                        </li>
                        <li>
                            If possible, include a staging URL where we can reproduce safely.
                        </li>
                        <li>
                            For agency retainers, mention your client name in the subject.
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="faq">
    <div class="container">
        <div class="top-section animation" data-animation="slideUp" data-delay="0.08s">
            <span class="label">Frequently Answer Questions</span>
            <h2>
                Getting Started Your Essential
                Questions Answered
            </h2>

        </div>
        <div class="accordion animation" data-animation="slideUp" data-delay="0.12s">
            <div class="box accordion-item is-active" data-accordion-item>
                <div class="single-accordion">
                    <button class="accordion-trigger" type="button" id="faq-trigger-01" aria-expanded="true"
                        aria-controls="faq-panel-01" data-accordion-trigger>
                        <div class="accordion-head d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center title-holder">
                                <span class="border">
                                    <span>01</span>
                                </span>
                                <h4 class="accordion-title">Why choose a suite instead of single plugins?</h4>
                            </div>
                            <span class="accordion-icon" aria-hidden="true">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path d="M5 8L10 13L15 8" stroke="currentColor" stroke-width="1.8"
                                        stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>
                        </div>
                    </button>
                    <div class="accordion-panel" id="faq-panel-01" role="region" aria-labelledby="faq-trigger-01"
                        data-accordion-panel>
                        <p>
                            Suites are curated combinations of plugins that work together out of the box – with a lower
                            price than buying each license separately.
                        </p>
                    </div>
                </div>
            </div>
            <div class="box accordion-item" data-accordion-item>
                <div class="single-accordion">
                    <button class="accordion-trigger" type="button" id="faq-trigger-02" aria-expanded="true"
                        aria-controls="faq-panel-02" data-accordion-trigger>
                        <div class="accordion-head d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center title-holder">
                                <span class="border">
                                    <span>02</span>
                                </span>
                                <h4 class="accordion-title">Why choose a suite instead of single plugins?</h4>
                            </div>
                            <span class="accordion-icon" aria-hidden="true">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path d="M5 8L10 13L15 8" stroke="currentColor" stroke-width="1.8"
                                        stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>
                        </div>
                    </button>
                    <div class="accordion-panel" id="faq-panel-02" role="region" aria-labelledby="faq-trigger-02"
                        data-accordion-panel>
                        <p>
                            Suites are curated combinations of plugins that work together out of the box – with a lower
                            price than buying each license separately.
                        </p>
                    </div>
                </div>
            </div>
            <div class="box accordion-item" data-accordion-item>
                <div class="single-accordion">
                    <button class="accordion-trigger" type="button" id="faq-trigger-03" aria-expanded="true"
                        aria-controls="faq-panel-03" data-accordion-trigger>
                        <div class="accordion-head d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center title-holder">
                                <span class="border">
                                    <span>03</span>
                                </span>
                                <h4 class="accordion-title">How can I access the My Dashboard?</h4>
                            </div>
                            <span class="accordion-icon" aria-hidden="true">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path d="M5 8L10 13L15 8" stroke="currentColor" stroke-width="1.8"
                                        stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>
                        </div>
                    </button>
                    <div class="accordion-panel" id="faq-panel-03" role="region" aria-labelledby="faq-trigger-03"
                        data-accordion-panel>
                        <p>
                            Suites are curated combinations of plugins that work together out of the box – with a lower
                            price than buying each license separately.
                        </p>
                    </div>
                </div>
            </div>
            <div class="box accordion-item" data-accordion-item>
                <div class="single-accordion">
                    <button class="accordion-trigger" type="button" id="faq-trigger-04" aria-expanded="true"
                        aria-controls="faq-panel-04" data-accordion-trigger>
                        <div class="accordion-head d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center title-holder">
                                <span class="border">
                                    <span>04</span>
                                </span>
                                <h4 class="accordion-title">One ecosystem, many plugins</h4>
                            </div>
                            <span class="accordion-icon" aria-hidden="true">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path d="M5 8L10 13L15 8" stroke="currentColor" stroke-width="1.8"
                                        stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>
                        </div>
                    </button>
                    <div class="accordion-panel" id="faq-panel-04" role="region" aria-labelledby="faq-trigger-04"
                        data-accordion-panel>
                        <p>
                            Suites are curated combinations of plugins that work together out of the box – with a lower
                            price than buying each license separately.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="faq-footer animation" data-animation="slideUp" data-delay="0.18s">
            <a href="docs.php" class="btn btn-primary">See more</a>
        </div>
    </div>
</section>
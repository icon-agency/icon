<?php

/**
 * @file
 * The two legal pages the footer's legal line links to, from the live
 * site's own words (24 Sep 2026; user ask: "create a privacy and raise
 * concern pages — https://iconagency.com.au/privacy
 * https://iconagency.com.au/raising-concern-about-icon").
 *
 * Each is a Canvas page found by its alias and created when there is none,
 * as about-page.php does: the Masthead (eyebrow Legal, the caps line, opens
 * on light grey and hands over) and one Content block per section — the
 * old page's h4s as h3s, its lists and links kept, the privacy policy's
 * effective date in the Small style. Running it again REBUILDS the page
 * from these words (editor changes are lost; the auto-save draft cleared).
 *
 * Run: ddev drush php:script scripts/legal-pages
 * Content source: iconagency.com.au/privacy, /raising-concern-about-icon
 * (24 Sep 2026).
 */

$uuid = \Drupal::service('uuid');
$storage = \Drupal::entityTypeManager()->getStorage('canvas_page');
$aliases = \Drupal::service('path_alias.repository');
$version = static function (string $id): string {
  $c = \Drupal::entityTypeManager()->getStorage('component')->load($id);
  if (!$c) {
    throw new \RuntimeException("Component $id is not registered.");
  }
  return (string) $c->get('active_version');
};

$pages = [
  [
    'alias' => "/privacy",
    'title' => "Privacy",
    'accent' => "Legal",
    'caps' => "Privacy policy",
    'description' => "How ICON Agency collects, uses, stores and discloses personal information, under the Privacy Act 1988 (Cth) and the Australian Privacy Principles.",
    'blocks' => [
  <<<'HTML'
<p class="is-small">Effective: 10 June 2025</p><p>This document sets forth the Privacy Policy for the Icon Agency website, www.iconagency.com.au.</p><p>Icon Agency is committed to protecting your privacy and ensuring your personal information is handled in accordance with the Privacy Act 1988 (Cth) and the Australian Privacy Principles (APPs). This policy outlines how we collect, use, store, and disclose your personal information.</p>
HTML,
  <<<'HTML'
<h3>1. Collection of Personal Information</h3><p>You can browse most of this site without providing personal information. However, access to certain services, such as customer support features, may require you to submit personally identifiable information. This may include, but is not limited to:</p><ul><li>Name</li><li>Contact details (e.g. email address, phone number)</li><li>Unique usernames and passwords</li><li>Sensitive information submitted for account recovery</li></ul><p>We only collect personal information that is reasonably necessary for our functions or activities.</p>
HTML,
  <<<'HTML'
<h3>2. Use of Personal Information</h3><p>We may use your personal information to:</p><ul><li>Provide requested services or support</li><li>Improve user experience and website performance</li><li>Conduct data analytics to enhance our service offerings</li><li>Communicate important notices or updates</li></ul><p>We also collect non-personally identifiable information such as browser type, operating system, pages visited, time on site, and referring websites. This is used solely for internal purposes such as analytics and website improvements.</p><p>If our data practices change in the future, we will update this policy accordingly. We will only apply these changes to information collected after the policy change.</p>
HTML,
  <<<'HTML'
<h3>3. Disclosure of Personal Information</h3><p>We may disclose your personal information to trusted third parties to provide services on our behalf. This may include:</p><ul><li>Customer service providers</li><li>Freight and logistics partners</li><li>Payment processors</li></ul><p>All third parties are required to comply with privacy obligations that meet or exceed the standards set out in this policy and the APPs.</p><p>We do not sell or rent personal information to third parties for marketing purposes.</p>
HTML,
  <<<'HTML'
<h3>4. Storage and Security of Personal Information</h3><p>We take reasonable steps to protect your personal information from unauthorised access, use, or disclosure. These steps include:</p><ul><li>Secure data storage and encryption</li><li>Access controls and authentication</li><li>Staff training on privacy obligations</li></ul>
HTML,
  <<<'HTML'
<h3>5. Data Retention and Deletion</h3><p>Icon Agency will retain your personal information only for as long as necessary to fulfil the purposes for which it was collected or to comply with legal or contractual obligations.</p><p>Unless otherwise required by law, personal information that is no longer required will be securely deleted or de-identified after 12 months from the date of collection.</p>
HTML,
  <<<'HTML'
<h3>6. Access and Correction</h3><p>You have the right to access the personal information we hold about you and request corrections if it is inaccurate, out of date, incomplete, or misleading. Requests should be made in writing to:<br>Attn: Privacy Policy<br>Icon Agency<br>132C Gwynne Street, Cremorne, VIC 3121<br>AUSTRALIA<br>E-mail: <a href="mailto:hello@iconagency.com.au">hello@iconagency.com.au</a></p>
HTML,
  <<<'HTML'
<h3>7. Changes to This Privacy Policy</h3><p>Icon Agency reserves the right to amend this Privacy Policy at any time. We recommend reviewing it periodically to stay informed of how we are protecting your information.</p><p>Continued use of the website after changes indicates your acceptance of the revised policy.</p>
HTML,
    ],
  ],
  [
    'alias' => "/raising-concern-about-icon",
    'title' => "Raising a concern about ICON",
    'accent' => "Legal",
    'caps' => "Raising a concern about ICON",
    'description' => "How employees, contractors, clients, suppliers, partners and members of the public can raise a concern about ICON, and how ICON responds.",
    'blocks' => [
  <<<'HTML'
<p>ICON is committed to responsible, ethical and transparent business practices.</p><p>We provide this page so employees, contractors, clients, suppliers, partners, community members and members of the public can raise a concern about ICON, our operations, our work, our people, our services or our impacts.</p><p>Concerns can be submitted by emailing <a href="mailto:speakup@iconagency.com.au"><strong>speakup@iconagency.com.au</strong></a>.</p>
HTML,
  <<<'HTML'
<h3>Who can raise a concern</h3><p>This process is available to both internal and external stakeholders, including:</p><ul><li>Employees</li><li>Contractors and freelancers</li><li>Clients and former clients</li><li>Suppliers and delivery partners</li><li>Business partners</li><li>Community members</li><li>Members of the public</li><li>Any person affected by ICON’s work, conduct, decisions or operations</li></ul>
HTML,
  <<<'HTML'
<h3>What concerns can be raised</h3><p>A grievance or concern may relate to ICON’s business practices, services, conduct, decisions, operations, client work or impact on stakeholders.</p><p>Examples of matters that may be raised include:</p><ul><li>Unlawful, unethical or improper conduct</li><li>A breach of ICON’s policies, values, professional standards or legal obligations</li><li>Human rights, social, environmental or community impact concerns</li><li>Concerns about work undertaken for clients in sensitive, controversial or high-impact sectors</li><li>Conflicts of interest, fraud, corruption, bribery or misuse of information</li><li>Bullying, harassment, discrimination, victimisation or unsafe behaviour</li><li>Privacy, data security, cyber security or responsible AI concerns</li><li>Modern slavery, labour rights or supplier conduct concerns</li><li>Retaliation or adverse treatment after raising a concern</li><li>Any issue where ICON’s conduct may have caused or contributed to harm</li></ul><p>Some matters may be better handled through another process, such as client service management, employment procedures, procurement processes, privacy enquiries or legal correspondence. Where this applies, ICON will explain why the matter has not been accepted under this grievance process and, where appropriate, direct the person to the most relevant contact or process.</p>
HTML,
  <<<'HTML'
<h3>How to raise a concern</h3><p>Concerns can be submitted by email to <a href="mailto:speakup@iconagency.com.au"><strong>speakup@iconagency.com.au</strong></a>.</p><p>To help us assess and respond to your concern, please include as much relevant information as possible, such as:</p><ul><li>Your name and contact details, unless you wish to remain anonymous</li><li>Your relationship to ICON</li><li>A description of the concern</li><li>The date, timeframe or location of the issue</li><li>Any ICON people, projects, clients, suppliers or activities involved</li><li>Any supporting documents, screenshots, links or evidence</li><li>Whether you are seeking confidentiality</li><li>Any outcome you are seeking</li></ul><p>You may raise a concern anonymously. ICON will consider anonymous reports where enough information is provided to allow the matter to be assessed. If contact details are not provided, ICON may not be able to seek further information or provide updates.</p>
HTML,
  <<<'HTML'
<h3>What happens after a concern is submitted</h3><p>ICON will manage concerns in a fair, confidential and timely way.</p><h5>1. Acknowledgement</h5><p>Where contact details are provided, ICON will acknowledge receipt of the concern within <strong>5 business days</strong>.</p><h5>2. Initial assessment</h5><p>ICON will assess the concern to determine whether it falls within this grievance process. This assessment will usually be completed within <strong>10 business days</strong> of receipt.</p><p>The initial assessment will consider:</p><ul><li>The nature of the concern</li><li>Whether the issue relates to ICON, our operations, our people, our work or our impacts</li><li>Whether another ICON process is more appropriate</li><li>Whether there are any immediate risks to people, confidentiality, safety or wellbeing</li><li>Whether any conflicts of interest need to be managed</li></ul><h5>3. Referral or escalation</h5><p>If the concern is accepted as a grievance, it will be referred to an appropriate senior person for review. Where possible, the person reviewing the matter will be independent of the issue raised.</p><p>Serious matters may be escalated to ICON’s directors, legal advisers, external advisers, regulators or authorities where appropriate or required.</p><p>If the concern is not accepted as a grievance, ICON will explain the reason where contact details have been provided. Where appropriate, ICON may direct the person to another process or contact point.</p><h5>4. Review or investigation</h5><p>ICON will review the concern and may seek further information from the person who raised it, relevant ICON team members, suppliers, clients, partners or other stakeholders.</p><p>The review process will depend on the nature and seriousness of the concern. It may include:</p><ul><li>Clarifying the facts</li><li>Reviewing documents, records, communications or project materials</li><li>Speaking with relevant people</li><li>Assessing policy, legal, ethical, client or supplier obligations</li><li>Considering actual or potential impacts on affected stakeholders</li><li>Assessing risks to confidentiality, safety, wellbeing or retaliation</li></ul><p>ICON aims to complete a review or provide a progress update within <strong>30 business days</strong> of accepting a grievance. Some matters may take longer due to complexity, availability of information, legal requirements or the involvement of external parties. Where this occurs, ICON will provide an update where possible.</p><h5>5. Resolution</h5><p>Where a grievance is substantiated, partly substantiated or identifies an opportunity for improvement, ICON will consider appropriate action.</p><p>Depending on the matter, this may include:</p><ul><li>Providing an explanation, correction or apology</li><li>Correcting an error, process or decision</li><li>Updating policies, procedures, training or governance</li><li>Taking management, disciplinary or contractual action</li><li>Engaging with affected stakeholders</li><li>Reviewing client, supplier or partner arrangements</li><li>Implementing safeguards to prevent recurrence</li><li>Escalating the matter to directors, legal advisers, regulators or authorities where appropriate</li></ul><p>ICON will seek to resolve grievances in a way that is fair, proportionate and practical, while considering the rights, privacy and safety of all people involved.</p><h5>6. Communication and closure</h5><p>Where contact details are provided, ICON will communicate with the person who raised the concern at key stages of the process.</p><p>This may include:</p><ul><li>Acknowledging receipt of the concern</li><li>Confirming whether the matter has been accepted as a grievance</li><li>Explaining the process and expected timeframes</li><li>Requesting further information where needed</li><li>Providing progress updates where appropriate</li><li>Confirming when the matter has been closed</li><li>Explaining, where possible, the outcome or resolution</li></ul><p>In some cases, ICON may not be able to provide detailed information about an outcome due to privacy, confidentiality, legal or employment obligations.</p>
HTML,
  <<<'HTML'
<h3>Whistleblower protection and non-retaliation</h3><p>ICON does not tolerate retaliation, victimisation, intimidation, harassment, discrimination, disadvantage or adverse treatment against any person who raises a concern in good faith or participates in a grievance process.</p><p>This protection applies to internal and external stakeholders, including employees, contractors, suppliers, clients, partners, community members and members of the public.</p><p>Retaliation may include:</p><ul><li>Dismissal, demotion or disciplinary action</li><li>Reduction in work, shifts, responsibilities or opportunities</li><li>Threats, intimidation or harassment</li><li>Discrimination, bullying or exclusion</li><li>Damage to reputation or business relationships</li><li>Unfair treatment in procurement, contracting or client relationships</li><li>Any other adverse action connected to raising a concern</li></ul><p>Any person who believes they have experienced retaliation after raising a concern should contact <a href="mailto:speakup@iconagency.com.au"><strong>speakup@iconagency.com.au</strong></a>.</p><p>A copy of ICON's Whistleblower Policy can be found <a href="https://drive.google.com/file/d/1CrAoeeJ1MotI9gIDP3Nlvf3O9q-fh8zC/view?usp=sharing">here</a>.</p>
HTML,
  <<<'HTML'
<h3>Confidentiality and protection</h3><p>ICON will take reasonable steps to protect people who raise concerns.</p><p>This includes:</p><ul><li>Treating reports confidentially where possible</li><li>Restricting access to information to people who need it to assess, investigate or resolve the matter</li><li>Managing conflicts of interest</li><li>Assessing risks to the person who raised the concern and other affected stakeholders</li><li>Considering whether additional safeguards are required before contacting people involved</li><li>Allowing concerns to be raised anonymously where enough information is provided</li><li>Managing records securely</li><li>Handling personal information in line with ICON’s privacy obligations</li></ul><p>ICON may need to disclose information in limited circumstances, including where required by law, where necessary to investigate or resolve a matter, where there is a serious risk to safety or wellbeing, or where disclosure is required to seek legal or professional advice.</p>
HTML,
  <<<'HTML'
<h3>Consequences for retaliation</h3><p>If retaliation is identified, ICON may take corrective action.</p><p>Depending on the circumstances, this may include:</p><ul><li>Disciplinary action for employees</li><li>Management action, training or monitoring</li><li>Changes to reporting lines, project roles or working arrangements</li><li>Contractual action involving contractors, suppliers or partners</li><li>Escalation to ICON’s directors</li><li>Referral to legal advisers, regulators or authorities where appropriate</li></ul>
HTML,
  <<<'HTML'
<h3>Responsible client work</h3><p>ICON reviews potential client work for legal, ethical, reputational and social impact risks.</p><p>Where a client, project or sector presents higher risk, ICON may undertake additional review before accepting or continuing the work. This may include consideration of the nature of the work, the likely impact on stakeholders, alignment with ICON’s values and policies, and whether ICON can contribute positively through responsible communications, accessible digital services, transparency, public information or ethical advice.</p><p>ICON may decline work that conflicts with our values, policies, legal obligations or responsible business commitments.</p>
HTML,
  <<<'HTML'
<h3>Related policies</h3><p>This grievance and whistleblower protection process is supported by ICON policies and governance practices, including:</p><ul><li>Code of Conduct</li><li>Whistleblower Policy</li><li>Anti-Discrimination, Bullying and Harassment Policy</li><li>Fraud and Corruption Policy</li><li>Modern Slavery Statement</li><li>ESG Policy</li><li>Safe and Responsible AI Policy</li><li>Privacy Policy</li></ul><p>Relevant policies may be made available to stakeholders, clients, partners or assurance bodies where appropriate.</p>
HTML,
    ],
  ],
];

foreach ($pages as $spec) {
  $page = NULL;
  $path = $aliases->lookupByAlias($spec['alias'], 'en');
  if ($path && preg_match('#^/page/(\d+)$#', $path['path'], $m)) {
    $page = $storage->load($m[1]);
  }
  if (!$page) {
    $page = $storage->create(['title' => $spec['title'], 'owner' => 1]);
    print "No {$spec['alias']} page yet: creating one.\n";
  }
  $tree = [];
  $add = static function (string $component, array $inputs, ?string $parent = NULL, ?string $slot = NULL) use (&$tree, $uuid, $version): string {
    $id = $uuid->generate();
    $tree[] = [
      'parent_uuid' => $parent,
      'slot' => $slot,
      'uuid' => $id,
      'component_id' => $component,
      'component_version' => $version($component),
      'inputs' => json_encode($inputs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
      'label' => NULL,
    ];
    return $id;
  };
  $add('sdc.icon.page-header', [
    'accent' => $spec['accent'],
    'caps' => $spec['caps'],
    'heading_level' => 'h1',
    'opening' => 'light-grey',
  ]);
  foreach ($spec['blocks'] as $html) {
    $add('sdc.icon.prose', ['text' => ['value' => $html, 'format' => 'canvas_html_block']]);
  }
  $page->set('title', $spec['title']);
  $page->set('description', $spec['description']);
  $page->set('components', $tree);
  $page->set('path', ['alias' => $spec['alias']]);
  $page->setPublished(TRUE);
  if ($page->hasField('moderation_state')) {
    $page->set('moderation_state', 'published');
  }
  $page->setNewRevision(TRUE);
  $page->setRevisionLogMessage("{$spec['title']} rebuilt from iconagency.com.au{$spec['alias']}.");
  $page->save();
  // The editor prefers an auto-save draft to the saved page, so clear any.
  \Drupal::service(\Drupal\canvas\AutoSave\AutoSaveManager::class)->delete($page);
  print "Saved canvas_page {$page->id()} with " . count($tree) . " components at " . $page->toUrl()->toString() . "\n";
}
print "Done.\n";

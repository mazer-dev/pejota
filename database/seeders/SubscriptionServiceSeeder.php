<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubscriptionServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->aiServices();
    }

    public function aiServices()
    {
        $services = [
            ['name' => 'OpenAI', 'description' => 'AI research and deployment company', 'url' => 'https://www.openai.com'],
            ['name' => 'Google AI', 'description' => 'AI research and development by Google', 'url' => 'https://ai.google'],
            ['name' => 'IBM Watson', 'description' => 'AI for business by IBM', 'url' => 'https://www.ibm.com/watson'],
            ['name' => 'Microsoft Azure AI', 'description' => 'AI services by Microsoft', 'url' => 'https://azure.microsoft.com/en-us/services/cognitive-services'],
            ['name' => 'Amazon AI', 'description' => 'AI services by Amazon', 'url' => 'https://aws.amazon.com/machine-learning'],
            ['name' => 'H2O.ai', 'description' => 'Open source AI platform', 'url' => 'https://www.h2o.ai'],
            ['name' => 'DataRobot', 'description' => 'Enterprise AI platform', 'url' => 'https://www.datarobot.com'],
            ['name' => 'C3.ai', 'description' => 'Enterprise AI software', 'url' => 'https://www.c3.ai'],
            ['name' => 'Salesforce Einstein', 'description' => 'AI for CRM by Salesforce', 'url' => 'https://www.salesforce.com/products/einstein'],
            ['name' => 'Clarifai', 'description' => 'AI for image and video recognition', 'url' => 'https://www.clarifai.com'],
            ['name' => 'SAS AI', 'description' => 'AI and analytics by SAS', 'url' => 'https://www.sas.com/en_us/solutions/ai.html'],
            ['name' => 'Affectiva', 'description' => 'Emotion AI technology', 'url' => 'https://www.affectiva.com'],
            ['name' => 'SoundHound', 'description' => 'Voice AI technology', 'url' => 'https://www.soundhound.com'],
            ['name' => 'x.ai', 'description' => 'AI scheduling assistant', 'url' => 'https://x.ai'],
            ['name' => 'UiPath', 'description' => 'Robotic process automation', 'url' => 'https://www.uipath.com'],
            ['name' => 'Blue Prism', 'description' => 'Robotic process automation', 'url' => 'https://www.blueprism.com'],
            ['name' => 'Automation Anywhere', 'description' => 'Robotic process automation', 'url' => 'https://www.automationanywhere.com'],
            ['name' => 'NVIDIA AI', 'description' => 'AI computing by NVIDIA', 'url' => 'https://www.nvidia.com/en-us/deep-learning-ai'],
            ['name' => 'DeepMind', 'description' => 'AI research lab by Alphabet', 'url' => 'https://deepmind.com'],
            ['name' => 'OpenCV', 'description' => 'Open source computer vision library', 'url' => 'https://opencv.org'],
            ['name' => 'Face++', 'description' => 'AI for facial recognition', 'url' => 'https://www.faceplusplus.com'],
            ['name' => 'Sift', 'description' => 'AI for fraud detection', 'url' => 'https://sift.com'],
            ['name' => 'Darktrace', 'description' => 'AI for cybersecurity', 'url' => 'https://www.darktrace.com'],
            ['name' => 'Vicarious', 'description' => 'AI for robotics', 'url' => 'https://www.vicarious.com'],
            ['name' => 'Zoox', 'description' => 'AI for autonomous vehicles', 'url' => 'https://zoox.com'],
            ['name' => 'Nuro', 'description' => 'AI for autonomous delivery', 'url' => 'https://www.nuro.ai'],
            ['name' => 'Cortical.io', 'description' => 'AI for natural language processing', 'url' => 'https://www.cortical.io'],
            ['name' => 'Sentient Technologies', 'description' => 'AI for trading and e-commerce', 'url' => 'https://www.sentient.ai'],
            ['name' => 'AIBrain', 'description' => 'AI for cognitive computing', 'url' => 'https://www.aibrain.com'],
            ['name' => 'DeepBrain', 'description' => 'AI for video synthesis', 'url' => 'https://www.deepbrain.io'],
            ['name' => 'Synthesia', 'description' => 'AI for video creation', 'url' => 'https://www.synthesia.io'],
            ['name' => 'Pictory', 'description' => 'AI for video editing', 'url' => 'https://www.pictory.ai'],
            ['name' => 'Runway', 'description' => 'AI for video editing and effects', 'url' => 'https://www.runwayml.com'],
            ['name' => 'Veed.io', 'description' => 'AI for video editing', 'url' => 'https://www.veed.io'],
            ['name' => 'Magisto', 'description' => 'AI for video creation and editing', 'url' => 'https://www.magisto.com'],
            ['name' => 'Animoto', 'description' => 'AI for video creation', 'url' => 'https://www.animoto.com'],
            ['name' => 'Lumen5', 'description' => 'AI for video creation from text', 'url' => 'https://www.lumen5.com'],
            ['name' => 'Wibbitz', 'description' => 'AI for video creation from text', 'url' => 'https://www.wibbitz.com'],
            ['name' => 'InVideo', 'description' => 'AI for video creation', 'url' => 'https://www.invideo.io'],
            ['name' => 'Vidnami', 'description' => 'AI for video creation', 'url' => 'https://www.vidnami.com'],
            ['name' => 'Rephrase.ai', 'description' => 'AI for personalized video creation', 'url' => 'https://www.rephrase.ai'],
            ['name' => 'Veed', 'description' => 'AI for video editing', 'url' => 'https://www.veed.io'],
            ['name' => 'Kapwing', 'description' => 'AI for video editing', 'url' => 'https://www.kapwing.com'],
            ['name' => 'Descript', 'description' => 'AI for video and audio editing', 'url' => 'https://www.descript.com'],
            ['name' => 'Jumptvs', 'description' => 'AI for video analytics', 'url' => 'https://www.jumptvs.com'],
            ['name' => 'Valossa', 'description' => 'AI for video content analysis', 'url' => 'https://www.valossa.com'],
            ['name' => 'Samba TV', 'description' => 'AI for video content recognition', 'url' => 'https://www.samba.tv'],
            ['name' => 'Zegami', 'description' => 'AI for video and image analysis', 'url' => 'https://www.zegami.com'],
            ['name' => 'Pixability', 'description' => 'AI for video advertising', 'url' => 'https://www.pixability.com'],
            ['name' => 'Vidooly', 'description' => 'AI for video analytics', 'url' => 'https://www.vidooly.com'],
            ['name' => 'Vidrovr', 'description' => 'AI for video search and analysis', 'url' => 'https://www.vidrovr.com'],
            ['name' => 'AnyClip', 'description' => 'AI for video content analysis', 'url' => 'https://www.anyclip.com'],
            ['name' => 'Wochit', 'description' => 'AI for video creation', 'url' => 'https://www.wochit.com'],
            ['name' => 'Vionlabs', 'description' => 'AI for video content analysis', 'url' => 'https://www.vionlabs.com'],
            ['name' => 'Syntheia', 'description' => 'AI for video creation', 'url' => 'https://www.syntheia.com'],
            ['name' => 'Deepgram', 'description' => 'AI for video transcription', 'url' => 'https://www.deepgram.com'],
            ['name' => 'Trint', 'description' => 'AI for video transcription', 'url' => 'https://www.trint.com'],
            ['name' => 'Veritone', 'description' => 'AI for video content analysis', 'url' => 'https://www.veritone.com'],

        ];

        foreach ($services as $service) {
            DB::table('subscription_services')->updateOrCreate(['name' => $service['name']], $service);
        }
    }

    public function cloud()
    {
        $services = [
            ['name' => 'Google Drive', 'description' => 'Cloud storage service by Google', 'url' => 'https://www.google.com/drive'],
            ['name' => 'Google Accounts', 'description' => 'Account management service by Google', 'url' => 'https://accounts.google.com'],
            ['name' => 'Google Cloud', 'description' => 'Cloud computing services by Google', 'url' => 'https://cloud.google.com'],
            ['name' => 'IBM Watson', 'description' => 'AI for business by IBM', 'url' => 'https://www.ibm.com/watson'],
            ['name' => 'Microsoft Azure AI', 'description' => 'AI services by Microsoft', 'url' => 'https://azure.microsoft.com/en-us/services/cognitive-services'],
            ['name' => 'Amazon AI', 'description' => 'AI services by Amazon', 'url' => 'https://aws.amazon.com/machine-learning'],
            ['name' => 'H2O.ai', 'description' => 'Open source AI platform', 'url' => 'https://www.h2o.ai'],
            ['name' => 'DataRobot', 'description' => 'Enterprise AI platform', 'url' => 'https://www.datarobot.com'],
            ['name' => 'C3.ai', 'description' => 'Enterprise AI software', 'url' => 'https://www.c3.ai'],
            ['name' => 'Salesforce Einstein', 'description' => 'AI for CRM by Salesforce', 'url' => 'https://www.salesforce.com/products/einstein'],
            ['name' => 'Clarifai', 'description' => 'AI for image and video recognition', 'url' => 'https://www.clarifai.com'],
            ['name' => 'SAS AI', 'description' => 'AI and analytics by SAS', 'url' => 'https://www.sas.com/en_us/solutions/ai.html'],
            ['name' => 'Affectiva', 'description' => 'Emotion AI technology', 'url' => 'https://www.affectiva.com'],
            ['name' => 'SoundHound', 'description' => 'Voice AI technology', 'url' => 'https://www.soundhound.com'],
            ['name' => 'x.ai', 'description' => 'AI scheduling assistant', 'url' => 'https://x.ai'],
            ['name' => 'UiPath', 'description' => 'Robotic process automation', 'url' => 'https://www.uipath.com'],
            ['name' => 'Blue Prism', 'description' => 'Robotic process automation', 'url' => 'https://www.blueprism.com'],
            ['name' => 'Automation Anywhere', 'description' => 'Robotic process automation', 'url' => 'https://www.automationanywhere.com'],
            ['name' => 'NVIDIA AI', 'description' => 'AI computing by NVIDIA', 'url' => 'https://www.nvidia.com/en-us/deep-learning-ai'],
            ['name' => 'DeepMind', 'description' => 'AI research lab by Alphabet', 'url' => 'https://deepmind.com'],
            ['name' => 'OpenCV', 'description' => 'Open source computer vision library', 'url' => 'https://opencv.org'],
            ['name' => 'Face++', 'description' => 'AI for facial recognition', 'url' => 'https://www.faceplusplus.com'],
            ['name' => 'Sift', 'description' => 'AI for fraud detection', 'url' => 'https://sift.com'],
            ['name' => 'Darktrace', 'description' => 'AI for cybersecurity', 'url' => 'https://www.darktrace.com'],
            ['name' => 'Vicarious', 'description' => 'AI for robotics', 'url' => 'https://www.vicarious.com'],
            ['name' => 'Zoox', 'description' => 'AI for autonomous vehicles', 'url' => 'https://zoox.com'],
            ['name' => 'Nuro', 'description' => 'AI for autonomous delivery', 'url' => 'https://www.nuro.ai'],
            ['name' => 'Cortical.io', 'description' => 'AI for natural language processing', 'url' => 'https://www.cortical.io'],
            ['name' => 'Sentient Technologies', 'description' => 'AI for trading and e-commerce', 'url' => 'https://www.sentient.ai'],
            ['name' => 'AIBrain', 'description' => 'AI for cognitive computing', 'url' => 'https://www.aibrain.com'],
            ['name' => 'Vicarious', 'description' => 'AI for robotics', 'url' => 'https://www.vicarious.com'],
            ['name' => 'Forge', 'description' => 'Forge is a cloud-based AI platform that helps developers build, train, and deploy machine learning models.', 'url' => 'https://forge.laravel.com'],
            ['name' => 'DigitalOcean', 'description' => 'Cloud computing services for developers', 'url' => 'https://www.digitalocean.com'],
            ['name' => 'Linode', 'description' => 'Cloud computing services for developers', 'url' => 'https://www.linode.com'],
            ['name' => 'Vultr', 'description' => 'Cloud computing services for developers', 'url' => 'https://www.vultr.com'],
            ['name' => 'OVHcloud', 'description' => 'Cloud computing services by OVH', 'url' => 'https://www.ovhcloud.com'],
            ['name' => 'Kamatera', 'description' => 'Cloud computing services', 'url' => 'https://www.kamatera.com'],
            ['name' => 'InMotion Hosting', 'description' => 'VPS hosting services', 'url' => 'https://www.inmotionhosting.com'],
            ['name' => 'HostGator', 'description' => 'VPS hosting services', 'url' => 'https://www.hostgator.com'],
            ['name' => 'Bluehost', 'description' => 'VPS hosting services', 'url' => 'https://www.bluehost.com'],
            ['name' => 'A2 Hosting', 'description' => 'VPS hosting services', 'url' => 'https://www.a2hosting.com'],
            ['name' => 'GreenGeeks', 'description' => 'VPS hosting services', 'url' => 'https://www.greengeeks.com'],
            ['name' => 'DreamHost', 'description' => 'VPS hosting services', 'url' => 'https://www.dreamhost.com'],
            ['name' => 'InterServer', 'description' => 'VPS hosting services', 'url' => 'https://www.interserver.net'],
            ['name' => 'Liquid Web', 'description' => 'VPS hosting services', 'url' => 'https://www.liquidweb.com'],
            ['name' => 'ScalaHosting', 'description' => 'VPS hosting services', 'url' => 'https://www.scalahosting.com'],
            ['name' => 'ScalaHosting', 'description' => 'VPS hosting services', 'url' => 'https://www.scalahosting.com'],
            ['name' => 'Hostinger', 'description' => 'VPS hosting services', 'url' => 'https://www.hostinger.com'],
            ['name' => 'Namecheap', 'description' => 'VPS hosting services', 'url' => 'https://www.namecheap.com'],
            ['name' => 'IONOS', 'description' => 'VPS hosting services', 'url' => 'https://www.ionos.com'],
            ['name' => 'SiteGround', 'description' => 'VPS hosting services', 'url' => 'https://www.siteground.com'],
            ['name' => 'GoDaddy', 'description' => 'VPS hosting services', 'url' => 'https://www.godaddy.com'],
            ['name' => 'AccuWeb Hosting', 'description' => 'VPS hosting services', 'url' => 'https://www.accuwebhosting.com'],
            ['name' => 'Hostwinds', 'description' => 'VPS hosting services', 'url' => 'https://www.hostwinds.com'],
            ['name' => 'TurnKey Internet', 'description' => 'VPS hosting services', 'url' => 'https://www.turnkeyinternet.net'],
            ['name' => 'RoseHosting', 'description' => 'VPS hosting services', 'url' => 'https://www.rosehosting.com'],
            ['name' => 'KnownHost', 'description' => 'VPS hosting services', 'url' => 'https://www.knownhost.com'],
            ['name' => 'TMDHosting', 'description' => 'VPS hosting services', 'url' => 'https://www.tmdhosting.com'],
            ['name' => 'VPS.net', 'description' => 'VPS hosting services', 'url' => 'https://www.vps.net'],
            ['name' => 'HostPapa', 'description' => 'VPS hosting services', 'url' => 'https://www.hostpapa.com'],
            ['name' => 'FastComet', 'description' => 'VPS hosting services', 'url' => 'https://www.fastcomet.com'],
            ['name' => 'BigRock', 'description' => 'VPS hosting services', 'url' => 'https://www.bigrock.in'],
            ['name' => 'JustHost', 'description' => 'VPS hosting services', 'url' => 'https://www.justhost.com'],
        ];

        foreach ($services as $service) {
            DB::table('subscription_services')->updateOrCreate(['name' => $service['name']], $service);
        }
    }

    public function educational()
    {
        $services = [
            ['name' => 'Coursera', 'description' => 'Online courses from top universities', 'url' => 'https://www.coursera.org'],
            ['name' => 'edX', 'description' => 'Online courses from top universities', 'url' => 'https://www.edx.org'],
            ['name' => 'Udemy', 'description' => 'Online courses on various topics', 'url' => 'https://www.udemy.com'],
            ['name' => 'Khan Academy', 'description' => 'Free online courses and resources', 'url' => 'https://www.khanacademy.org'],
            ['name' => 'LinkedIn Learning', 'description' => 'Professional development courses', 'url' => 'https://www.linkedin.com/learning'],
            ['name' => 'Skillshare', 'description' => 'Online learning community', 'url' => 'https://www.skillshare.com'],
            ['name' => 'Pluralsight', 'description' => 'Technology and creative courses', 'url' => 'https://www.pluralsight.com'],
            ['name' => 'FutureLearn', 'description' => 'Online courses from top universities', 'url' => 'https://www.futurelearn.com'],
            ['name' => 'Codecademy', 'description' => 'Learn to code interactively', 'url' => 'https://www.codecademy.com'],
            ['name' => 'Treehouse', 'description' => 'Online coding courses', 'url' => 'https://teamtreehouse.com'],
            ['name' => 'MasterClass', 'description' => 'Online classes from experts', 'url' => 'https://www.masterclass.com'],
            ['name' => 'Brilliant', 'description' => 'Interactive learning in math and science', 'url' => 'https://www.brilliant.org'],
            ['name' => 'Udacity', 'description' => 'Nanodegree programs and courses', 'url' => 'https://www.udacity.com'],
            ['name' => 'Lynda', 'description' => 'Online courses for creative and business skills', 'url' => 'https://www.lynda.com'],
            ['name' => 'Academic Earth', 'description' => 'Free online college courses', 'url' => 'https://www.academicearth.org'],
            ['name' => 'Alison', 'description' => 'Free online courses with certificates', 'url' => 'https://www.alison.com'],
            ['name' => 'OpenLearn', 'description' => 'Free learning from The Open University', 'url' => 'https://www.open.edu/openlearn'],
            ['name' => 'Saylor Academy', 'description' => 'Free and open online courses', 'url' => 'https://www.saylor.org'],
            ['name' => 'MIT OpenCourseWare', 'description' => 'Free lecture notes, exams, and videos from MIT', 'url' => 'https://ocw.mit.edu'],
            ['name' => 'Harvard Online Learning', 'description' => 'Online courses from Harvard University', 'url' => 'https://online-learning.harvard.edu'],
            ['name' => 'Stanford Online', 'description' => 'Online courses from Stanford University', 'url' => 'https://online.stanford.edu'],
            ['name' => 'Yale Online', 'description' => 'Online courses from Yale University', 'url' => 'https://online.yale.edu'],
            ['name' => 'Open Yale Courses', 'description' => 'Free and open access to Yale courses', 'url' => 'https://oyc.yale.edu'],
            ['name' => 'Carnegie Mellon Open Learning Initiative', 'description' => 'Free online courses from Carnegie Mellon University', 'url' => 'https://oli.cmu.edu'],
            ['name' => 'University of London Online', 'description' => 'Online courses from the University of London', 'url' => 'https://london.ac.uk/courses'],
            ['name' => 'Oxford University Online', 'description' => 'Online courses from the University of Oxford', 'url' => 'https://www.conted.ox.ac.uk'],
            ['name' => 'Berkeley Online', 'description' => 'Online courses from UC Berkeley', 'url' => 'https://online.berkeley.edu'],
            ['name' => 'Coursera for Business', 'description' => 'Online courses for businesses', 'url' => 'https://www.coursera.org/business'],
            ['name' => 'Edureka', 'description' => 'Online courses for professional development', 'url' => 'https://www.edureka.co'],
            ['name' => 'Simplilearn', 'description' => 'Online courses for professional certification', 'url' => 'https://www.simplilearn.com'],
            ['name' => 'The Great Courses', 'description' => 'Online courses on various topics', 'url' => 'https://www.thegreatcourses.com'],
        ];

        foreach ($services as $service) {
            DB::table('subscription_services')->updateOrCreate(['name' => $service['name']], $service);
        }
    }

    public function streaming()
    {
        $services = [
            ['name' => 'Netflix', 'description' => 'Streaming service for movies and TV shows', 'url' => 'https://www.netflix.com'],
            ['name' => 'Hulu', 'description' => 'Streaming service for TV shows and movies', 'url' => 'https://www.hulu.com'],
            ['name' => 'Disney+', 'description' => 'Streaming service for Disney movies and TV shows', 'url' => 'https://www.disneyplus.com'],
            ['name' => 'HBO Max', 'description' => 'Streaming service for HBO content', 'url' => 'https://www.hbomax.com'],
            ['name' => 'Amazon Prime Video', 'description' => 'Streaming service for movies, TV shows, and original content', 'url' => 'https://www.amazon.com/primevideo'],
            ['name' => 'Apple TV+', 'description' => 'Streaming service for Apple original content', 'url' => 'https://www.apple.com/apple-tv-plus'],
            ['name' => 'Peacock', 'description' => 'Streaming service for NBC content', 'url' => 'https://www.peacocktv.com'],
            ['name' => 'Paramount+', 'description' => 'Streaming service for CBS and Paramount content', 'url' => 'https://www.paramountplus.com'],
            ['name' => 'YouTube TV', 'description' => 'Live TV streaming service by YouTube', 'url' => 'https://tv.youtube.com'],
            ['name' => 'Sling TV', 'description' => 'Live TV streaming service', 'url' => 'https://www.sling.com'],
            ['name' => 'FuboTV', 'description' => 'Live sports and TV streaming service', 'url' => 'https://www.fubo.tv'],
            ['name' => 'Crunchyroll', 'description' => 'Streaming service for anime', 'url' => 'https://www.crunchyroll.com'],
            ['name' => 'Funimation', 'description' => 'Streaming service for anime', 'url' => 'https://www.funimation.com'],
            ['name' => 'Tubi', 'description' => 'Free streaming service for movies and TV shows', 'url' => 'https://www.tubi.tv'],
            ['name' => 'Pluto TV', 'description' => 'Free live TV streaming service', 'url' => 'https://www.pluto.tv'],
            ['name' => 'Vudu', 'description' => 'Streaming service for movies and TV shows', 'url' => 'https://www.vudu.com'],
            ['name' => 'Shudder', 'description' => 'Streaming service for horror movies and TV shows', 'url' => 'https://www.shudder.com'],
            ['name' => 'Acorn TV', 'description' => 'Streaming service for British TV shows', 'url' => 'https://www.acorn.tv'],
            ['name' => 'BritBox', 'description' => 'Streaming service for British TV shows', 'url' => 'https://www.britbox.com'],
            ['name' => 'Starz', 'description' => 'Streaming service for movies and original content', 'url' => 'https://www.starz.com'],
            ['name' => 'Showtime', 'description' => 'Streaming service for movies and original content', 'url' => 'https://www.showtime.com'],
            ['name' => 'Discovery+', 'description' => 'Streaming service for Discovery Channel content', 'url' => 'https://www.discoveryplus.com'],
            ['name' => 'ESPN+', 'description' => 'Streaming service for sports content', 'url' => 'https://www.espn.com/espnplus'],
            ['name' => 'DAZN', 'description' => 'Streaming service for sports content', 'url' => 'https://www.dazn.com'],
            ['name' => 'Philo', 'description' => 'Live TV streaming service', 'url' => 'https://www.philo.com'],
            ['name' => 'AT&T TV', 'description' => 'Live TV streaming service', 'url' => 'https://www.att.com/tv'],
            ['name' => 'CuriosityStream', 'description' => 'Streaming service for documentaries', 'url' => 'https://www.curiositystream.com'],
            ['name' => 'Kanopy', 'description' => 'Free streaming service for movies and documentaries', 'url' => 'https://www.kanopy.com'],
            ['name' => 'Mubi', 'description' => 'Streaming service for independent and classic films', 'url' => 'https://www.mubi.com'],
            ['name' => 'The Criterion Channel', 'description' => 'Streaming service for classic and contemporary films', 'url' => 'https://www.criterionchannel.com'],
            ['name' => 'Spotify', 'description' => 'Music streaming service', 'url' => 'https://www.spotify.com'],
        ];

        foreach ($services as $service) {
            DB::table('subscription_services')->updateOrCreate(['name' => $service['name']], $service);
        }
    }
}

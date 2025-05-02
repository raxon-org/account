/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- Dumping data for table p343606_wat_prod.application: ~5 rows (approximately)
INSERT INTO `application` (`id`, `uuid`, `name`, `url`, `icon_url`) VALUES
    (1, '11fb658d-eef6-421f-867c-81ca7d2cd18d', 'Audio Player', "{{route.get('application-audio-player')}}", "{{route.get('application-audio-player-icon')}}"),
    (2, '7dfd23bc-31af-47ba-ad82-74fbfc807f84', 'Ace Editor',"{{route.get('application-ace-editor')}}", "{{route.get('application-ace-editor-icon')}}"),
    (6, '854febe0-c23b-4afb-8b18-a45ba2d562c4', 'Photo Viewer', "{{route.get('application-photo-viewer')}}", "{{route.get('application-photo-viewer-icon')}}"),
    (7, '96749beb-d808-4a95-be56-b66f40ccabed', 'Video Player', "{{route.get('application-video-player')}}", "{{route.get('application-video-player-icon')}}"),
    (8, 'd03d57e8-d39d-40bb-89b7-b5a81343d1bd', 'Music Player', "{{route.get('application-music-player')}}", "{{route.get('application-audio-player-icon')}}");
/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;

USE master;
GO

-- Jika database MIBD sudah ada, hapus dulu
IF EXISTS (SELECT * FROM sys.databases WHERE name = 'MIBD')
BEGIN
    ALTER DATABASE MIBD SET SINGLE_USER WITH ROLLBACK IMMEDIATE;
    DROP DATABASE MIBD;
END
GO

-- Buat database baru
CREATE DATABASE MIBD;
GO

-- **Pindah konteks ke MIBD**
USE MIBD;
GO

-- 1) Hapus FOREIGN KEY constraints yang masih mengikat Channel
IF OBJECT_ID('FK_Video_Channel', 'F') IS NOT NULL
    ALTER TABLE dbo.Video    DROP CONSTRAINT FK_Video_Channel;
IF OBJECT_ID('FK_AdaRole_Channel', 'F') IS NOT NULL
    ALTER TABLE dbo.AdaRole   DROP CONSTRAINT FK_AdaRole_Channel;
IF OBJECT_ID('FK_Subscribe_Channel', 'F') IS NOT NULL
    ALTER TABLE dbo.Subscribe DROP CONSTRAINT FK_Subscribe_Channel;

-- 2) Hapus FOREIGN KEY constraints yang masih mengikat Video
IF OBJECT_ID('FK_View_Video', 'F') IS NOT NULL
    ALTER TABLE dbo.[View]    DROP CONSTRAINT FK_View_Video;
IF OBJECT_ID('FK_Reaksi_Video', 'F') IS NOT NULL
    ALTER TABLE dbo.Reaksi    DROP CONSTRAINT FK_Reaksi_Video;
IF OBJECT_ID('FK_Komen_Video', 'F') IS NOT NULL
    ALTER TABLE dbo.Komen     DROP CONSTRAINT FK_Komen_Video;
GO

-- 3) Drop tabel dari child → parent
IF OBJECT_ID('dbo.Komen',      'U') IS NOT NULL DROP TABLE dbo.Komen;
IF OBJECT_ID('dbo.Reaksi',     'U') IS NOT NULL DROP TABLE dbo.Reaksi;
IF OBJECT_ID('dbo.[View]',     'U') IS NOT NULL DROP TABLE dbo.[View];
IF OBJECT_ID('dbo.Subscribe',  'U') IS NOT NULL DROP TABLE dbo.Subscribe;
IF OBJECT_ID('dbo.AdaRole',    'U') IS NOT NULL DROP TABLE dbo.AdaRole;
IF OBJECT_ID('dbo.Invite',     'U') IS NOT NULL DROP TABLE dbo.Invite;
IF OBJECT_ID('dbo.Video',      'U') IS NOT NULL DROP TABLE dbo.Video;
IF OBJECT_ID('dbo.Channel',    'U') IS NOT NULL DROP TABLE dbo.Channel;
IF OBJECT_ID('dbo.BrandAcc',   'U') IS NOT NULL DROP TABLE dbo.BrandAcc;
IF OBJECT_ID('dbo.[User]',     'U') IS NOT NULL DROP TABLE dbo.[User];
GO

-- 4) (Re)create tabel–tabel sesuai dependency
CREATE TABLE dbo.[User] (
    userId    INT IDENTITY(1,1) PRIMARY KEY,
	username  NVARCHAR(15) not null,
    email     NVARCHAR(255)  NOT NULL UNIQUE,
    pass      NVARCHAR(255)  NOT NULL,
);
GO

CREATE TABLE dbo.BrandAcc (
    baId    INT           IDENTITY(1,1) PRIMARY KEY,
    nama    NVARCHAR(100) NOT NULL,
    userId  INT           NOT NULL,
    pfp     NVARCHAR(255) NULL,
    CONSTRAINT FK_BrandAcc_User FOREIGN KEY(userId) REFERENCES dbo.[User](userId)
);
GO

CREATE TABLE dbo.Channel (
    chnlId  INT             IDENTITY(1,1) PRIMARY KEY,
    banner  NVARCHAR(255)   NULL,
    nama    NVARCHAR(100)   NOT NULL,
    [desc]  NVARCHAR(MAX)   NULL,
    pfp     NVARCHAR(255)   NULL,
    tipe    NVARCHAR(50)    NULL,
    baId    INT             NULL,
    userId  INT             NULL,
    CONSTRAINT FK_Channel_BrandAcc FOREIGN KEY(baId)   REFERENCES dbo.BrandAcc(baId),
    CONSTRAINT FK_Channel_User     FOREIGN KEY(userId) REFERENCES dbo.[User](userId)
);
GO

CREATE TABLE dbo.Video (
    videoId   INT IDENTITY(1,1) PRIMARY KEY,
    tglUpld   DATE      NOT NULL,
    judul     NVARCHAR(200) NOT NULL,
    [desc]    NVARCHAR(MAX) NULL,
    durasi    INT           NOT NULL,
    status    NVARCHAR(50)  NOT NULL,
    chnlId    INT           NOT NULL,
    userId    INT           NOT NULL,
    thumbnail NVARCHAR(255) NULL,
    subtitle  NVARCHAR(255) NULL,
	playback  NVARCHAR(255) NULL,
    CONSTRAINT FK_Video_Channel FOREIGN KEY(chnlId) REFERENCES dbo.Channel(chnlId),
    CONSTRAINT FK_Video_User    FOREIGN KEY(userId) REFERENCES dbo.[User](userId)
);
GO

CREATE TABLE dbo.Invite (
    kirimId   INT           NOT NULL,
    terimaId  INT           NOT NULL,
    role      NVARCHAR(50)  NOT NULL,
    CONSTRAINT PK_Invite PRIMARY KEY (kirimId, terimaId, role),
    CONSTRAINT FK_Invite_KirimUser  FOREIGN KEY(kirimId)  REFERENCES dbo.[User](userId),
    CONSTRAINT FK_Invite_TerimaUser FOREIGN KEY(terimaId) REFERENCES dbo.[User](userId)
);
GO

CREATE TABLE dbo.AdaRole (
    userId   INT           NOT NULL,
    chnlId   INT           NOT NULL,
    role     NVARCHAR(50)  NOT NULL,
    CONSTRAINT PK_AdaRole          PRIMARY KEY (userId, chnlId, role),
    CONSTRAINT FK_AdaRole_User     FOREIGN KEY(userId)  REFERENCES dbo.[User](userId),
    CONSTRAINT FK_AdaRole_Channel  FOREIGN KEY(chnlId)  REFERENCES dbo.Channel(chnlId)
);
GO

CREATE TABLE dbo.Subscribe (
    userId   INT      NOT NULL,
    chnlId   INT      NOT NULL,
    tglSub   DATETIME NOT NULL DEFAULT GETDATE(),
    CONSTRAINT PK_Subscribe            PRIMARY KEY (userId, chnlId),
    CONSTRAINT FK_Subscribe_User       FOREIGN KEY(userId)  REFERENCES dbo.[User](userId),
    CONSTRAINT FK_Subscribe_Channel    FOREIGN KEY(chnlId)  REFERENCES dbo.Channel(chnlId)
);
GO

CREATE TABLE dbo.[View] (
    userId    INT      NOT NULL,
    videoId   INT      NOT NULL,
    durasi    INT      NOT NULL,
    tglView   DATE     NOT NULL,
    waktuView TIME     NOT NULL,
    CONSTRAINT PK_View          PRIMARY KEY (userId, videoId),
    CONSTRAINT FK_View_User     FOREIGN KEY(userId)  REFERENCES dbo.[User](userId),
    CONSTRAINT FK_View_Video    FOREIGN KEY(videoId) REFERENCES dbo.Video(videoId)
);
GO

CREATE TABLE dbo.Reaksi (
    userId  INT           NOT NULL,
    videoId INT           NOT NULL,
    tipe    NVARCHAR(20)  NOT NULL,
    CONSTRAINT PK_Reaksi           PRIMARY KEY (userId, videoId),
    CONSTRAINT FK_Reaksi_User      FOREIGN KEY(userId)  REFERENCES dbo.[User](userId),
    CONSTRAINT FK_Reaksi_Video     FOREIGN KEY(videoId) REFERENCES dbo.Video(videoId)
);
GO

CREATE TABLE dbo.Komen (
    userId       INT           NOT NULL,
    videoId      INT           NOT NULL,
    konten       NVARCHAR(MAX) NOT NULL,
    tanggalKomen DATE          NOT NULL,
    CONSTRAINT PK_Komen          PRIMARY KEY (userId, videoId),
    CONSTRAINT FK_Komen_User     FOREIGN KEY(userId)  REFERENCES dbo.[User](userId),
    CONSTRAINT FK_Komen_Video    FOREIGN KEY(videoId) REFERENCES dbo.Video(videoId)
);
GO

INSERT INTO dbo.[User]
VALUES 
('a', 'a@gmail.com', 'a'),
('w', 'w@gmail.com', 'a'),
('s', 's@gmail.com', 'a'),
('d', 'd@gmail.com', 'a'),
('j', 'j@gmail.com', 'j')

select * from [User]



INSERT INTO dbo.Channel (banner, nama, [desc], pfp, tipe, baId, userId)
VALUES 
(NULL, 'My Personal Channel', 'Personal vlog channel about daily life', NULL, 'personal', NULL, 1),
(NULL, 'Tech Explorations', 'Exploring latest technology trends', NULL, 'personal', NULL, 2),
(NULL, 'Cooking Adventures', 'Sharing my culinary journey', NULL, 'personal', NULL, 3),
(NULL, 'Gaming Universe', 'Daily gaming streams and reviews', NULL, 'personal', NULL, 4),
(NULL, 'Test channel name', 'Test channel desc', NULL, 'personal', NULL, 5);

select * from Channel



INSERT INTO dbo.Video (tglUpld, judul, [desc], durasi, status, chnlId, userId, thumbnail, subtitle, playback)
VALUES
(GETDATE(), 'My First Vlog', 'Sharing my first day as a content creator', 360, 'up', 1, 1, '../img/thumb1.png', NULL, '../Videos/video_1.mp4'),
(GETDATE(), 'huh', 'h', 123, 'up', 1, 1, '../img/thumb7.png', NULL, '../Videos/video_2.mp4'),
(GETDATE(), 'Tech Review: New Smartphone', 'Unboxing and testing the latest smartphone', 480, 'up', 2, 2, '../img/thumb2.png', NULL, '../Videos/video_3.mp4'),
(GETDATE(), 'Easy Pasta Recipe', 'Quick dinner recipe you can make in 15 minutes', 600, 'up', 3, 3, '../img/thumb3.png', NULL, '../Videos/video_4.mp4'),
(GETDATE(), 'Gameplay: Level 1-5 Walkthrough', 'Beginner guide for new players', 720, 'up', 4, 4, '../img/thumb4.png', NULL, '../Videos/video_5.mp4'),
(GETDATE(), 'Test title', 'Test desc', 100, 'up', 5, 5, '../img/thumb5.png', NULL, '../Videos/video_6.mp4'),
(GETDATE(), 'Test title', 'Test desc', 100, 'up', 5, 5, '../img/thumb6.png', NULL, '../Videos/video_7.mp4');

select * from Video


INSERT INTO dbo.[View] (userId, videoId, durasi, tglView, waktuView)
VALUES
-- User 2 watches video 1
(2, 1, 180, '2024-06-01', '14:30:00'),

-- User 2 watches video 2
(2, 2, 300, '2024-06-02', '16:45:00'),

-- User 1 watches video 1
(1, 1, 240, '2024-06-03', '10:15:00'),

-- User 1 watches video 3
(1, 3, 420, '2024-06-04', '19:20:00'),

-- User 3 watches video 1
(3, 1, 420, '2024-06-04', '19:20:00');

select * from [View]



INSERT INTO Reaksi
VALUES
(2, 1, 'like'),
(2, 2, 'dislike'),
(1, 1, 'dislike'),
(1, 3, 'like'),
(3, 1, 'dislike')

select * from Reaksi



insert into Komen
values
(1, 1, 'waw', GETDATE())

select * from Komen